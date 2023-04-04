<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import PointInfoPopup from '../../mixins/PointInfoPopup.js'
import BringTrackToTop from '../../mixins/BringTrackToTop.js'
import AddWaypoints from '../../mixins/AddWaypoints.js'
import { COLOR_CRITERIAS, getColorHueInInterval } from '../../constants.js'
import { getPaces } from '../../mapUtils.js'
import { LngLat } from 'maplibre-gl'

export default {
	name: 'TrackSimpleGradient',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		PointInfoPopup,
		BringTrackToTop,
		AddWaypoints,
	],

	props: {
		track: {
			type: Object,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		colorCriteria: {
			type: Number,
			default: COLOR_CRITERIAS.elevation.id,
		},
		colorExtensionCriteria: {
			type: String,
			default: null,
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
		}
	},

	computed: {
		layerId() {
			return String(this.track.id)
		},
		borderLayerId() {
			return this.layerId + '-border'
		},
		invisibleBorderLayerId() {
			return this.layerId + '-invisible-border'
		},
		color() {
			return this.track.color ?? '#0693e3'
		},
		onTop() {
			return this.track.onTop
		},
		trackGeojsonData() {
			console.debug('[gpxpod] compute track geojson', this.track.geojson)
			// use short point list for hovered track when we don't have the data yet
			if (!this.track.geojson) {
				return {
					type: 'FeatureCollection',
					features: [
						{
							type: 'Feature',
							geometry: {
								coordinates: this.track.short_point_list.map((p) => [p[1], p[0]]),
								type: 'LineString',
							},
						},
					],
				}
			} else {
				return this.track.geojson
			}
		},
		trackGeojsonSegments() {
			if (!this.track.geojson) {
				return null
			} else {
				const result = []
				this.track.geojson.features.forEach((feature) => {
					if (feature.geometry.type === 'LineString') {
						result.push(this.getFeatureCollectionFromCoords(feature.geometry.coordinates))
					} else if (feature.geometry.type === 'MultiLineString') {
						feature.geometry.coordinates.forEach((coords) => {
							result.push(this.getFeatureCollectionFromCoords(coords))
						})
					}
				})
				return result
			}
		},
		getPointValues() {
			return this.colorExtensionCriteria
				? (coords) => {
					return coords.map(c => c[4]?.unsupported?.[this.colorExtensionCriteria] ?? null)
				}
				: this.colorCriteria === COLOR_CRITERIAS.elevation.id
					? (coords) => {
						return coords.map(c => c[2])
					}
					: this.colorCriteria === COLOR_CRITERIAS.pace.id
						? getPaces
						: () => null
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
		trackGeojsonData() {
			console.debug('[gpxpod] trackGeojsonData has changed')
			this.remove()
			this.init()
		},
		colorCriteria() {
			this.onColorCriteriaChanged()
		},
		colorExtensionCriteria() {
			this.onColorCriteriaChanged()
		},
	},

	mounted() {
		console.debug('[gpxpod] track mounted!!!!!', String(this.track.id))
		this.init()
	},

	destroyed() {
		console.debug('[gpxpod] destroy track', String(this.track.id))
		this.remove()
	},

	methods: {
		getFeatureCollectionFromCoords(coords) {
			const geojson = {
				type: 'FeatureCollection',
				features: [
					{
						type: 'Feature',
						geometry: {
							coordinates: coords,
							type: 'LineString',
						},
					},
				],
			}

			return {
				// steps: [0, 'blue', 0.5, 'red', 1, 'yellow'],
				steps: this.getColorSteps(coords),
				geojson,
			}
		},
		getColorSteps(coords) {
			const pointValues = this.getPointValues(coords)
			const cleanValues = pointValues.filter(v => v !== undefined)
			const min = Math.min.apply(null, cleanValues)
			const max = Math.max.apply(null, cleanValues)
			console.debug('[gpxpod] simple gradient pointvalues', pointValues, 'min', min, 'max', max)
			const result = []
			const accTraveledDistances = [0]
			let prevLL = new LngLat(coords[0][0], coords[0][1])
			let totalDistance = 0
			for (let i = 1; i < coords.length; i++) {
				const currLL = new LngLat(coords[i][0], coords[i][1])
				const distance = prevLL.distanceTo(currLL)
				totalDistance = totalDistance + distance
				accTraveledDistances.push(totalDistance)
				prevLL = currLL
			}
			let prevPcDistance = null
			pointValues.forEach((val, i) => {
				const pcDistance = accTraveledDistances[i] / totalDistance
				if (pcDistance !== prevPcDistance) {
					prevPcDistance = pcDistance
					result.push(pcDistance)
					if (val !== null && val !== undefined) {
						result.push(this.getColor(min, max, val))
					} else {
						result.push('white')
					}
				}
			})
			return result
		},
		getColor(min, max, value) {
			const weight = (value - min) / (max - min)
			const hue = getColorHueInInterval(240, 0, weight)
			return 'hsl(' + hue + ', 100%, 50%)'
		},
		bringToTop() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}
			if (this.map.getLayer(this.layerId)) {
				this.map.moveLayer(this.layerId)
			}
			this.trackGeojsonSegments.forEach((seg, i) => {
				if (this.map.getLayer(this.layerId + '-seg-' + i)) {
					this.map.moveLayer(this.layerId + '-seg-' + i)
				}
			})
		},
		onMouseEnter() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth * 1.7)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', (this.lineWidth * 1.6) * 1.7)
			}
			this.trackGeojsonSegments.forEach((seg, i) => {
				if (this.map.getLayer(this.layerId + '-seg-' + i)) {
					this.map.setPaintProperty(this.layerId + '-seg-' + i, 'line-width', this.lineWidth * 1.7)
				}
			})
		},
		onMouseLeave() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 1.6)
			}
			this.trackGeojsonSegments.forEach((seg, i) => {
				if (this.map.getLayer(this.layerId + '-seg-' + i)) {
					this.map.setPaintProperty(this.layerId + '-seg-' + i, 'line-width', this.lineWidth)
				}
			})
		},
		onColorCriteriaChanged() {
			// a bit special, we need to take care of the waypoints here because we can't watch colorCriteria
			// in the AddWaypoints mixin
			this.removeWaypoints()

			this.remove()
			this.init()

			this.initWaypoints()
			this.listenToWaypointEvents()
		},
		remove() {
			if (this.map.getLayer(this.layerId)) {
				this.map.removeLayer(this.layerId)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.removeLayer(this.borderLayerId)
			}
			if (this.map.getLayer(this.invisibleBorderLayerId)) {
				this.map.removeLayer(this.invisibleBorderLayerId)
			}
			this.trackGeojsonSegments.forEach((seg, i) => {
				if (this.map.getLayer(this.layerId + '-seg-' + i)) {
					this.map.removeLayer(this.layerId + '-seg-' + i)
				}
				if (this.map.getSource(this.layerId + '-seg-' + i)) {
					this.map.removeSource(this.layerId + '-seg-' + i)
				}
			})
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},
		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.trackGeojsonData,
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
			if (this.trackGeojsonSegments === null) {
				this.map.addLayer({
					type: 'line',
					source: this.layerId,
					id: this.layerId,
					paint: {
						'line-color': this.color,
						'line-width': this.lineWidth,
					},
					layout: {
						'line-cap': 'round',
						'line-join': 'round',
					},
					filter: ['!=', '$type', 'Point'],
				})
			} else {
				this.trackGeojsonSegments.forEach((seg, i) => {
					console.debug('addddd seg', i, seg)
					this.map.addSource(this.layerId + '-seg-' + i, {
						type: 'geojson',
						lineMetrics: true,
						data: seg.geojson,
					})
					this.map.addLayer({
						type: 'line',
						source: this.layerId + '-seg-' + i,
						id: this.layerId + '-seg-' + i,
						paint: {
							'line-width': this.lineWidth,
							'line-gradient': [
								'interpolate',
								['linear'],
								['line-progress'],
								...seg.steps,
								/*
								0, 'red',
								0.1123456, 'red',
								0.2123456, 'red',
								0.3123456, 'green',
								0.4123456, 'green',
								0.5123456, 'green',
								0.6289308176100629, 'blue',
								0.7123456, 'blue',
								0.8123456, 'blue',
								0.9433962264150944, 'blue',
								1, 'purple',
								*/
							],
						},
						layout: {
							'line-cap': 'round',
							'line-join': 'round',
						},
						// filter: ['!=', '$type', 'Point'],
					})
				})
			}

			this.ready = true
		},
	},
	render(h) {
		return null
	},
}
</script>
