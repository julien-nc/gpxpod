<script>
export default {
	name: 'TrackGradient',

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
		trackGeojsonData() {
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
							properties: {
								color: this.color,
							},
						},
					],
				}
			} else {
				const result = {
					type: 'FeatureCollection',
					features: [],
				}
				this.track.geojson.features.forEach((feature) => {
					if (feature.geometry.type === 'LineString') {
						result.features.push(...this.getFeaturesFromCoords(feature.geometry.coordinates))
					} else if (feature.geometry.type === 'MultiLineString') {
						feature.geometry.coordinates.forEach((coords) => {
							result.features.push(...this.getFeaturesFromCoords(coords))
						})
					}
				})
				return result
			}
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
		getFeaturesFromCoords(coords) {
			if (coords.length < 2) {
				return [this.buildFeature(coords, this.color)]
			} else {
				const { min, max } = this.getMinMaxValue(coords)
				const features = []
				for (let fi = 0; fi < coords.length - 1; fi++) {
					features.push(this.buildFeature([coords[fi], coords[fi + 1]], this.getColor(min, max, coords[fi][2] ?? 0)))
				}
				return features
			}
		},
		getMinMaxValue(coords) {
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
		getColor(min, max, value) {
			const weight = (value - min) / (max - min)
			const hue = ((1 - weight) * 120).toString(10)
			return 'hsl(' + hue + ',100%,50%)'
		},
		buildFeature(coords, color) {
			return {
				type: 'Feature',
				geometry: {
					coordinates: coords,
					type: 'LineString',
				},
				properties: {
					color,
				},
			}
		},
		bringToTop() {
			if (this.map.getLayer(this.stringId) && this.map.getLayer(this.stringId + 'b')) {
				this.map.moveLayer(this.stringId + 'b')
				this.map.moveLayer(this.stringId)
			}
		},
		remove() {
			if (this.map.getLayer(this.stringId)) {
				this.map.removeLayer(this.stringId)
				this.map.removeLayer(this.stringId + 'b')
			}
			if (this.map.getSource(this.stringId)) {
				this.map.removeSource(this.stringId)
			}
		},
		init() {
			this.map.addSource(this.stringId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.trackGeojsonData,
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
			this.map.addLayer({
				type: 'line',
				source: this.stringId,
				id: this.stringId,
				paint: {
					'line-color': ['get', 'color'],
					'line-width': this.lineWidth,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})

			this.map.on('mouseenter', this.stringId + 'b', () => {
				this.bringToTop()
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
