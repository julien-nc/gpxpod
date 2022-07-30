<script>
export default {
	name: 'Track',

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
			console.debug('-------------------------compute track geojson', this.track.geojson)
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
	},

	watch: {
		color(newVal) {
			if (this.map.getLayer(this.stringId)) {
				this.map.setPaintProperty(this.stringId, 'line-color', newVal)
			}
		},
		onTop(newVal) {
			if (newVal) {
				this.bringToTop()
			}
		},
		trackGeojsonData() {
			console.debug('watch trackGeojsonData')
			this.remove()
			this.init()
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
					// to get from properties, do:
					// 'line-color': ['get', 'color'],
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
					// 'line-color': ['get', 'color'],
					'line-color': this.color,
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

			/*
			// gradient, need to be computed, it applies to each feature which might be annoying
			const stops = [
				0, 'cyan',
				0.2, 'cyan',
				0.6, 'orange',
				0.9, 'green',
				1, 'red',
			]
			this.map.addLayer({
				type: 'line',
				source: this.stringId,
				id: this.stringId,
				paint: {
					'line-width': 14,
					'line-gradient': [
						'interpolate',
						['linear'],
						['line-progress'],
						...stops,
					],
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})
			*/

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
