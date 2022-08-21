<script>
import { Popup } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { metersToDistance } from '../../utils.js'

const LAYER_SUFFIXES = {
	CLUSTERS: 'clusters',
	CLUSTERS_COUNT: 'cluster-count',
	UNCLUSTERED_POINT: 'unclustered-point',
}

const CIRCLE_RADIUS = 12

export default {
	name: 'MarkerCluster',

	components: {
	},

	mixins: [],

	props: {
		tracks: {
			type: Array,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		circleBorderColor: {
			type: String,
			default: 'black',
		},
	},

	data() {
		return {
			ready: false,
			stringId: 'cluster',
			hoverPopup: null,
			clickPopups: {},
			currentHoveredTrack: null,
		}
	},

	computed: {
		clusterGeojsonData() {
			const features = this.tracks.map((track) => {
				return {
					type: 'Feature',
					properties: {
						id: track.id,
						color: track.color,
						name: track.name,
						date_begin: track.date_begin,
						total_distance: track.total_distance,
						directoryId: track.directoryId,
					},
					geometry: {
						type: 'Point',
						coordinates: [track.lon, track.lat],
					},
				}
			})
			const geojson = {
				type: 'FeatureCollection',
				features,
			}
			return geojson
		},
	},

	watch: {
		clusterGeojsonData(n) {
			console.debug('CLUSTER tracks changed', n)
			this.remove()
			this.init()
		},
	},

	mounted() {
		this.init()
	},

	destroyed() {
		this.remove()
	},

	methods: {
		remove() {
			Object.values(LAYER_SUFFIXES).forEach((s) => {
				if (this.map.getLayer(this.stringId + s)) {
					this.map.removeLayer(this.stringId + s)
				}
			})
			if (this.map.getSource(this.stringId)) {
				this.map.removeSource(this.stringId)
			}
			// release event handlers
			this.map.off('click', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointClick)
			this.map.off('mouseenter', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointMouseEnter)
			this.map.off('mouseleave', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointMouseLeave)

			this.map.off('click', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterClick)
			this.map.off('mouseenter', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterMouseEnter)
			this.map.off('mouseleave', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterMouseLeave)
		},
		bringToTop() {
			Object.values(LAYER_SUFFIXES).forEach((s) => {
				if (this.map.getLayer(this.stringId + s)) {
					this.map.moveLayer(this.stringId + s)
				}
			})
		},
		init() {
			this.map.addSource(this.stringId, {
				type: 'geojson',
				data: this.clusterGeojsonData,
				cluster: true,
				clusterMaxZoom: 14,
				clusterRadius: 50,
			})

			this.map.addLayer({
				id: this.stringId + LAYER_SUFFIXES.CLUSTERS,
				type: 'circle',
				source: this.stringId,
				filter: ['has', 'point_count'],
				paint: {
					'circle-color': [
						'step',
						['get', 'point_count'],
						'#51bbd6',
						100,
						'#f1f075',
						750,
						'#f28cb1',
					],
					'circle-radius': [
						'step',
						['get', 'point_count'],
						20,
						100,
						30,
						750,
						40,
					],
				},
			})

			this.map.addLayer({
				id: this.stringId + LAYER_SUFFIXES.CLUSTERS_COUNT,
				type: 'symbol',
				source: this.stringId,
				filter: ['has', 'point_count'],
				layout: {
					'text-field': '{point_count_abbreviated}',
					'text-font': ['DIN Offc Pro Medium', 'Arial Unicode MS Bold'],
					'text-size': 12,
				},
			})

			this.map.addLayer({
				id: this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT,
				type: 'circle',
				source: this.stringId,
				filter: ['!', ['has', 'point_count']],
				paint: {
					'circle-color': ['get', 'color'],
					'circle-radius': 12,
					'circle-stroke-width': 2,
					'circle-stroke-color': this.circleBorderColor,
				},
			})

			this.map.on('click', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointClick)
			this.map.on('mouseenter', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointMouseEnter)
			this.map.on('mouseleave', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointMouseLeave)

			this.map.on('click', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterClick)
			this.map.on('mouseenter', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterMouseEnter)
			this.map.on('mouseleave', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterMouseLeave)

			this.ready = true
		},
		onUnclusteredPointClick(e) {
			const coordinates = e.features[0].geometry.coordinates.slice()
			const track = e.features[0].properties

			// Ensure that if the map is zoomed out such that
			// multiple copies of the feature are visible, the
			// popup appears over the copy being pointed to.
			while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
				coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360
			}

			// avoid adding multiple popups for the same marker
			if (!this.clickPopups[track.id]) {
				const html = '<div class="with-button" style="border-color: ' + (track.color ?? 'blue') + ';">'
					+ '<strong>' + t('gpxpod', 'Name') + '</strong>: ' + track.name
					+ '<br>'
					+ '<strong>' + t('gpxpod', 'Start') + '</strong>: ' + moment(track.date_begin).format('YYYY-MM-DD HH:mm:ss (Z)')
					+ '<br>'
					+ '<strong>' + t('gpxpod', 'Total distance') + '</strong>: ' + metersToDistance(track.total_distance)
					+ '</div>'
				const popup = new Popup({
					offset: CIRCLE_RADIUS,
					maxWidth: '240px',
					closeButton: true,
					closeOnClick: false,
					closeOnMove: false,
				})
					.setLngLat(coordinates)
					.setHTML(html)

				popup.on('close', () => { delete this.clickPopups[track.id] })
				popup.addTo(this.map)
				this.clickPopups[track.id] = popup
			}
		},
		onUnclusteredPointMouseEnter(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.bringToTop()

			// display a popup
			const coordinates = e.features[0].geometry.coordinates.slice()
			const track = e.features[0].properties
			const html = '<div style="border-color: ' + (track.color ?? 'blue') + ';">'
				+ '<strong>' + t('gpxpod', 'Name') + '</strong>: ' + track.name
				+ '</div>'
			this.hoverPopup = new Popup({
				offset: CIRCLE_RADIUS,
				maxWidth: '240px',
				closeButton: false,
				closeOnClick: true,
				closeOnMove: true,
			})
				.setLngLat(coordinates)
				.setHTML(html)
				.addTo(this.map)

			this.currentHoveredTrack = track
			this.$emit('track-marker-hover-in', { trackId: track.id, dirId: track.directoryId })
		},
		onUnclusteredPointMouseLeave(e) {
			this.map.getCanvas().style.cursor = ''
			this.hoverPopup?.remove()
			this.hoverPopup = null

			this.$emit('track-marker-hover-out', { trackId: this.currentHoveredTrack.id, dirId: this.currentHoveredTrack.directoryId })
			this.currentHoveredTrack = null
		},
		onClusterClick(e) {
			const features = this.map.queryRenderedFeatures(e.point, {
				layers: [this.stringId + LAYER_SUFFIXES.CLUSTERS],
			})
			const clusterId = features[0].properties.cluster_id
			this.map.getSource(this.stringId).getClusterExpansionZoom(
				clusterId,
				(err, zoom) => {
					if (err) {
						return
					}

					this.map.easeTo({
						center: features[0].geometry.coordinates,
						zoom,
					})
				},
			)
		},
		onClusterMouseEnter(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.bringToTop()
		},
		onClusterMouseLeave(e) {
			this.map.getCanvas().style.cursor = ''
		},
	},
	render(h) {
		if (this.ready && this.$slots.default) {
			return h('div', { style: { display: 'none' } }, this.$slots.default)
		}
		return null
	},
}
</script>
