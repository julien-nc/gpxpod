<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import BringTrackToTop from '../../mixins/BringTrackToTop.js'

import { metersToDistance, metersToElevation, formatDuration } from '../../utils.js'

import { basename } from '@nextcloud/paths'
import { LngLat, Popup } from 'maplibre-gl'

export default {
	name: 'ComparisonTrack',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		BringTrackToTop,
	],

	props: {
		geojson: {
			type: Object,
			required: true,
		},
		comparisonCriteria: {
			type: String,
			default: 'time',
		},
		map: {
			type: Object,
			required: true,
		},
		lineWidth: {
			type: Number,
			default: 5,
		},
		borderColor: {
			type: String,
			default: 'black',
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			ready: false,
			onTop: false,
			nonPersistentPopup: null,
			popups: [],
		}
	},

	computed: {
		layerId() {
			return String(this.geojson.id)
		},
		borderLayerId() {
			return this.layerId + '-border'
		},
		invisibleBorderLayerId() {
			return this.layerId + '-invisible-border'
		},
		color() {
			return '#0693e3'
		},
		processedGeojsonData() {
			console.debug('[gpxpod] compute comparison geojson', this.geojson)
			// use short point list for hovered track when we don't have the data yet
			const result = {
				type: 'FeatureCollection',
				features: this.geojson.features.map(f => {
					return {
						...f,
						properties: {
							...f.properties,
							color: this.getFeatureColor(f, this.comparisonCriteria),
						},
					}
				}),
			}
			return result
		},
	},

	watch: {
		color(newVal) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-color', newVal)
			}
		},
		onTop(newVal) {
			if (newVal) {
				this.bringToTop()
			}
		},
		processedGeojsonData() {
			console.debug('[gpxpod] processedGeojsonData has changed')
			this.remove()
			this.init()
		},
	},

	mounted() {
		console.debug('[gpxpod] comparison track mounted!!!!!', String(this.geojson.id))
		this.init()
	},

	unmounted() {
		console.debug('[gpxpod] destroy comparison track', String(this.geojson.id))
		this.remove()
	},

	methods: {
		getFeatureColor(feature, criteria) {
			const props = feature.properties
			if (criteria === 'time') {
				if (props.quicker) {
					return 'lightgreen'
				} else if (props.slower) {
					return 'red'
				}
			} else if (criteria === 'distance') {
				if (props.shorter) {
					return 'lightgreen'
				} else if (props.longer) {
					return 'red'
				}
			} else if (criteria === 'elevation') {
				if (props.lessPositiveDeniv) {
					return 'lightgreen'
				} else if (props.morePositiveDeniv) {
					return 'red'
				}
			}
			return this.color
		},
		bringToTop() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}
			if (this.map.getLayer(this.layerId)) {
				this.map.moveLayer(this.layerId)
			}
		},
		listenToPointInfoEvents() {
			this.map.on('click', this.invisibleBorderLayerId, this.onClickPoint)
			this.map.on('mouseenter', this.invisibleBorderLayerId, this.onMouseEnter)
			this.map.on('mouseleave', this.invisibleBorderLayerId, this.onMouseLeave)
		},
		releasePointInfoEvents() {
			this.map.off('click', this.invisibleBorderLayerId, this.onClickPoint)
			this.map.off('mouseenter', this.invisibleBorderLayerId, this.onMouseEnter)
			this.map.off('mouseleave', this.invisibleBorderLayerId, this.onMouseLeave)
		},
		onMouseEnter(e) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth * 1.7)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', (this.lineWidth * 1.6) * 1.7)
			}
			this.map.getCanvas().style.cursor = 'pointer'
			this.showPointPopup(e, false)
		},
		onMouseLeave() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 1.6)
			}
			this.map.getCanvas().style.cursor = ''
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
		},
		onClickPoint(e) {
			this.showPointPopup(e, true)
		},
		showPointPopup(event, persist = false) {
			const content = this.getPopupContent(event.features[0])
			const containerClass = persist ? 'class="with-button"' : ''
			const html = '<div ' + containerClass + ' style="border-color: ' + this.color + ';">' + content + '</div>'
			const popup = new Popup({
				closeButton: persist,
				closeOnClick: !persist,
				closeOnMove: !persist,
			})
				.setLngLat(this.findPointLngLat(event.lngLat))
				.setHTML(html)
				.addTo(this.map)
			if (persist) {
				this.popups.push(popup)
			} else {
				this.nonPersistentPopup = popup
			}
		},
		getPopupContent(feature) {
			const props = feature.properties
			if (props.time) {
				const distanceColor = props.longer
					? 'red'
					: props.shorter
						? 'green'
						: 'blue'
				const d1 = metersToDistance(props.distance, this.settings.distance_unit)
				const d2 = metersToDistance(props.distanceOther, this.settings.distance_unit)
				const distanceText = props.longer
					? t('gpxpod', '{d1} is longer than {d2}', { d1, d2 })
					: props.shorter
						? t('gpxpod', '{d1} is shorter than {d2}', { d1, d2 })
						: t('gpxpod', '{d1} is equal to {d2}', { d1, d2 })

				const timeColor = props.slower
					? 'red'
					: props.quicker
						? 'green'
						: 'blue'
				const t1 = formatDuration(props.time)
				const t2 = formatDuration(props.timeOther)
				const timeText = props.slower
					? t('gpxpod', '{t1} is slower than {t2}', { t1, t2 })
					: props.quicker
						? t('gpxpod', '{t1} is quicker than {t2}', { t1, t2 })
						: t('gpxpod', '{t1} is equal to {t2}', { t1, t2 })

				const denivColor = props.morePositiveDeniv
					? 'red'
					: props.lessPositiveDeniv
						? 'green'
						: 'blue'
				const e1 = metersToElevation(props.positiveDeniv, this.settings.distance_unit)
				const e2 = metersToElevation(props.positiveDenivOther, this.settings.distance_unit)
				const denivText = props.morePositiveDeniv
					? t('gpxpod', '{e1} climbs more than {e2}', { e1, e2 })
					: props.lessPositiveDeniv
						? t('gpxpod', '{e1} climbs less than {e2}', { e1, e2 })
						: t('gpxpod', '{e1} is equal to {e2}', { e1, e2 })

				return '<h2>' + basename(this.geojson.id) + '</h2>'
					+ '<strong style="color: ' + distanceColor + ';">' + t('gpxpod', 'Distance') + '</strong>: '
					+ '<span>' + distanceText + '</span><br>'
					+ '<strong style="color: ' + timeColor + ';">' + t('gpxpod', 'Time') + '</strong>: '
					+ '<span>' + timeText + '</span><br>'
					+ '<strong style="color: ' + denivColor + ';">' + t('gpxpod', 'Cumulative elevation gain') + '</strong>: '
					+ '<span>' + denivText + '</span><br>'
			}
			return t('gpxpod', 'There is no divergence here')
		},
		findPointLngLat(hoverLngLat) {
			let minDist = 40000000
			let minDistPoint = null
			let tmpDist
			this.geojson.features.forEach(feature => {
				if (feature.geometry.type === 'LineString') {
					for (let i = 0; i < feature.geometry.coordinates.length; i++) {
						const c = feature.geometry.coordinates[i]
						tmpDist = hoverLngLat.distanceTo(new LngLat(c[0], c[1]))
						if (tmpDist < minDist) {
							minDist = tmpDist
							minDistPoint = c
						}
					}
				}
			})
			return minDistPoint
		},
		remove() {
			if (this.map.getLayer(this.layerId)) {
				this.map.removeLayer(this.layerId)
				this.map.removeLayer(this.borderLayerId)
				this.map.removeLayer(this.invisibleBorderLayerId)
			}
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
			this.releasePointInfoEvents()
		},
		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.processedGeojsonData,
			})
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.invisibleBorderLayerId,
				paint: {
					'line-opacity': 0,
					'line-width': Math.max(this.lineWidth, 30),
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.borderLayerId,
				paint: {
					'line-color': this.borderColor,
					'line-width': this.lineWidth * 1.6,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
				filter: ['!=', '$type', 'Point'],
			})
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.layerId,
				paint: {
					'line-color': ['get', 'color'],
					'line-width': this.lineWidth,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
				filter: ['!=', '$type', 'Point'],
			})

			this.ready = true
			this.listenToPointInfoEvents()
		},
	},
	render(h) {
		return null
	},
}
</script>
