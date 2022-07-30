<script>
import { getColorGradientColors } from '../../constants'
const gradientColors = getColorGradientColors(120, 0)

export default {
	name: 'TrackGradientColorPoints',

	components: {
	},

	mixins: [],

	props: {
		track: {
			type: Object,
			required: true,
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
	},

	data() {
		return {
			ready: false,
		}
	},

	computed: {
		stringId() {
			return String(this.track.id)
		},
		color() {
			return this.track.color ?? '#0693e3'
		},
		onTop() {
			return this.track.onTop
		},
		// return an object indexed by color index, 2 levels, first color and second color
		// first color index is always lower than second (or equal)
		geojsonsPerColorPair() {
			const result = {}
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					this.addFeaturesFromCoords(result, feature.geometry.coordinates)
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						this.addFeaturesFromCoords(result, coords)
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
	},

	mounted() {
		this.init()
	},

	destroyed() {
		console.debug('destroy track ' + this.stringId)
		this.remove()
	},

	methods: {
		addFeaturesFromCoords(geojsons, coords) {
			if (coords.length < 2) {
				this.addFeature(geojsons, coords, 0, 0)
			} else {
				const { min, max } = this.getMinMaxValue(coords)
				// process the first pair outside the loop
				let colorIndex = this.getColorIndex(min, max, coords[0][2])
				colorIndex = this.processPair(geojsons, min, max, colorIndex, coords[0], coords[1])
				// loop starts with the 2nd pair
				for (let fi = 1; fi < coords.length - 1; fi++) {
					colorIndex = this.processPair(geojsons, min, max, colorIndex, coords[fi], coords[fi + 1])
				}
			}
		},
		processPair(geojsons, min, max, firstColorIndex, coord1, coord2) {
			const secondColorIndex = this.getColorIndex(min, max, coord2[2])
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
		getMinMaxValue(coords) {
			// TODO avoid potential first null values to init min/max
			let min = coords[0][2]
			let max = coords[0][2]
			for (let i = 1; i < coords.length; i++) {
				if (coords[i][2]) {
					if (coords[i][2] > max) max = coords[i][2]
					if (coords[i][2] < min) min = coords[i][2]
				}
			}
			return { min, max }
		},
		getColorIndex(min, max, value) {
			if (value === null) {
				return 0
			}
			return Math.floor((value - min) / (max - min) * 10)
		},
		bringToTop() {
			if (this.map.getLayer(this.stringId + 'b')) {
				this.map.moveLayer(this.stringId + 'b')
			}

			const pairData = this.geojsonsPerColorPair
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.stringId + '-' + ci1 + '-' + ci2
					if (this.map.getLayer(pairId)) {
						this.map.moveLayer(pairId)
					}
				})
			})
		},
		remove() {
			// remove border
			if (this.map.getLayer(this.stringId + 'b')) {
				this.map.removeLayer(this.stringId + 'b')
			}
			if (this.map.getSource(this.stringId)) {
				this.map.removeSource(this.stringId)
			}
			// remove colored lines
			const pairData = this.geojsonsPerColorPair
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.stringId + '-' + ci1 + '-' + ci2
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
			this.map.addSource(this.stringId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.track.geojson,
			})
			this.map.addLayer({
				type: 'line',
				source: this.stringId,
				id: this.stringId + 'b',
				paint: {
					'line-color': this.borderColor,
					'line-width': this.lineWidth * 1.6,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})

			this.map.on('mouseenter', this.stringId + 'b', () => {
				this.bringToTop()
			})

			// colored lines
			const pairData = this.geojsonsPerColorPair
			console.debug('[gpxpod] TrackGradientColorPoints: pair data', pairData)
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.stringId + '-' + ci1 + '-' + ci2
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
