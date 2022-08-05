<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor'
import MethodOnBorderHover from '../../mixins/MethodOnBorderHover'
import { COLOR_CRITERIAS, getColorGradientColors } from '../../constants'
import { LngLat } from 'maplibre-gl'

const gradientColors = getColorGradientColors(240, 0)

/**
 * Assign a color to each point according to point-specific values
 * Problem: we can only color segments when drawing.
 * Part of the solution: gradients
 * Gradients are not easy to manipulate in maplibregl so we use a trick:
 * We divide the color space in 10 (11 actually) so we can assign one "color range" per point.
 * We create one layer per existing color pairs (2 consecutive points form a pair).
 * Each layer contains all related point pairs as LineStrings.
 * One layer defines the gradient corresponding to the 2 related colors.
 * This gradient will be used for each of its LineString.
 */
export default {
	name: 'TrackGradientColorPoints',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		MethodOnBorderHover,
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
			default: COLOR_CRITERIAS.elevation.value,
		},
		lineWidth: {
			type: Number,
			default: 5,
		},
		borderColor: {
			type: String,
			default: 'black',
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
			return String(this.track.id) + '-border'
		},
		color() {
			return this.track.color ?? '#0693e3'
		},
		onTop() {
			return this.track.onTop
		},
		getPointValues() {
			return this.colorCriteria === COLOR_CRITERIAS.elevation.value
				? (coords) => {
					return coords.map(c => c[2])
				}
				: this.colorCriteria === COLOR_CRITERIAS.pace.value
					? this.getPace
					: () => null
		},
		// return an object indexed by color index, 2 levels, first color and second color
		// first color index is always lower than second (or equal)
		geojsonsPerColorPair() {
			const result = {}
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					// we artificially use this.getPointValues here to make sure geojsonsPerColorPair
					// gets re-computed when the color criteria changes
					this.addFeaturesFromCoords(result, feature.geometry.coordinates, this.getPointValues)
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						this.addFeaturesFromCoords(result, coords, this.getPointValues)
					})
				}
			})
			return result
		},
	},

	watch: {
		onTop(newVal) {
			if (newVal) {
				this.bringToTop()
			}
		},
		colorCriteria() {
			this.remove()
			this.init()
		},
	},

	mounted() {
		this.init()
	},

	destroyed() {
		console.debug('destroy track ' + this.layerId)
		this.remove()
	},

	methods: {
		addFeaturesFromCoords(geojsons, coords) {
			if (coords.length < 2) {
				this.addFeature(geojsons, coords, 0, 0)
			} else {
				const pointValues = this.getPointValues(coords)
				const cleanValues = pointValues.filter(v => v !== undefined)
				const min = Math.min.apply(null, cleanValues)
				const max = Math.max.apply(null, cleanValues)
				console.debug('[gpxpod] pointvalues', pointValues, 'min', min, 'max', max)
				// process the first pair outside the loop, we need 2 color indexes to form a pair :-)
				let colorIndex = this.getColorIndex(min, max, pointValues[0])
				colorIndex = this.processPair(geojsons, min, max, colorIndex, coords[0], coords[1], pointValues[1])
				// loop starts with the 2nd pair
				for (let fi = 1; fi < coords.length - 1; fi++) {
					colorIndex = this.processPair(geojsons, min, max, colorIndex, coords[fi], coords[fi + 1], pointValues[fi + 1])
				}
			}
		},
		processPair(geojsons, min, max, firstColorIndex, coord1, coord2, secondPointValue) {
			const secondColorIndex = this.getColorIndex(min, max, secondPointValue)
			if (secondColorIndex > firstColorIndex) {
				this.buildFeature(geojsons, firstColorIndex, secondColorIndex, coord1, coord2)
			} else {
				this.buildFeature(geojsons, secondColorIndex, firstColorIndex, coord2, coord1)
			}
			return secondColorIndex
		},
		buildFeature(geojsons, colorIndex1, colorIndex2, coord1, coord2) {
			if (!geojsons[colorIndex1]) {
				geojsons[colorIndex1] = {}
			}
			if (!geojsons[colorIndex1][colorIndex2]) {
				geojsons[colorIndex1][colorIndex2] = {
					type: 'FeatureCollection',
					features: [],
				}
			}
			geojsons[colorIndex1][colorIndex2].features.push({
				type: 'Feature',
				geometry: {
					coordinates: [coord1, coord2],
					type: 'LineString',
				},
			})
		},
		getColorIndex(min, max, value) {
			if (value === null) {
				return 0
			}
			return Math.floor((value - min) / (max - min) * 10)
		},
		getPace(coords) {
			const timestamps = coords.map(c => c[3])
			const lngLats = coords.map((c) => new LngLat(c[0], c[1]))
			const paces = []

			let i, distanceToPrev
			let j = 0
			let distWindow = 0

			let distanceFromStart = 0
			paces.push(0)

			// if there is a missing time : pace is 0
			for (i = 0; i < coords.length; i++) {
				if (!timestamps[i]) {
					return coords.map(c => 0)
				}
			}

			for (i = 1; i < coords.length; i++) {
				// in km
				distanceToPrev = lngLats[i - 1].distanceTo(lngLats[i]) / 1000
				distanceFromStart += distanceToPrev
				distWindow += distanceToPrev

				if (distanceFromStart < 1) {
					paces.push(0)
				} else {
					// get the pace (time to do the last km/mile) for this point
					while (j < i && distWindow > 1) {
						j++
						distWindow = distWindow - (lngLats[j - 1].distanceTo(lngLats[j]) / 1000)
						/*
						if (unit === 'metric') {
							distWindow = distWindow - (gpxpod.map.distance([latlngs[j - 1][0], latlngs[j - 1][1]], [latlngs[j][0], latlngs[j][1]]) / 1000)
						} else if (unit === 'nautical') {
							distWindow = distWindow - (METERSTONAUTICALMILES * gpxpod.map.distance([latlngs[j - 1][0], latlngs[j - 1][1]], [latlngs[j][0], latlngs[j][1]]))
						} else if (unit === 'english') {
							distWindow = distWindow - (METERSTOMILES * gpxpod.map.distance([latlngs[j - 1][0], latlngs[j - 1][1]], [latlngs[j][0], latlngs[j][1]]))
						}
						*/
					}
					// the j to consider is j-1 (when dist between j and i is more than 1)
					// in minutes
					paces.push((timestamps[i] - timestamps[j - 1]) / 60)
				}
			}
			return paces
		},
		bringToTop() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}

			const pairData = this.geojsonsPerColorPair
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-' + ci1 + '-' + ci2
					if (this.map.getLayer(pairId)) {
						this.map.moveLayer(pairId)
					}
				})
			})
		},
		remove() {
			this.releaseBorderHover()
			// remove border
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.removeLayer(this.borderLayerId)
			}
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
			// remove colored lines
			const pairData = this.geojsonsPerColorPair
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-' + ci1 + '-' + ci2
					if (this.map.getLayer(pairId)) {
						this.map.removeLayer(pairId)
					}
					if (this.map.getSource(pairId)) {
						this.map.removeSource(pairId)
					}
				})
			})
		},
		init() {
			// border
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.track.geojson,
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
			})

			// colored lines
			const pairData = this.geojsonsPerColorPair
			console.debug('[gpxpod] TrackGradientColorPoints: pair data', pairData)
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-' + ci1 + '-' + ci2
					this.map.addSource(pairId, {
						type: 'geojson',
						lineMetrics: true,
						data: pairData[ci1][ci2],
					})
					if (ci1 === ci2) {
						this.map.addLayer({
							type: 'line',
							source: pairId,
							id: pairId,
							paint: {
								'line-color': gradientColors[ci1],
								'line-width': this.lineWidth,
							},
							layout: {
								'line-cap': 'round',
								'line-join': 'round',
							},
						})
					} else {
						this.map.addLayer({
							type: 'line',
							source: pairId,
							id: pairId,
							paint: {
								'line-color': 'red',
								'line-width': this.lineWidth,
								'line-gradient': [
									'interpolate',
									['linear'],
									['line-progress'],
									0,
									gradientColors[ci1],
									1,
									gradientColors[ci2],
								],
							},
							layout: {
								'line-cap': 'round',
								'line-join': 'round',
							},
						})
					}
				})
			})

			this.listenToBorderHover()

			this.ready = true
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
