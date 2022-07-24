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
	},

	data() {
		return {
			ready: false,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		this.init()
	},

	destroyed() {
		console.debug('destroy track ' + this.track.id)
		this.remove()
	},

	methods: {
		remove() {
			if (this.map.getLayer(this.track.id)) {
				this.map.removeLayer(this.track.id)
			}
			if (this.map.getSource(this.track.id)) {
				this.map.removeSource(this.track.id)
			}
		},
		init() {
			this.map.addSource(this.track.id, {
				type: 'geojson',
				lineMetrics: true,
				data: this.track.geojson,
			})
			/*
			// this is funny but too thin
			this.map.addLayer({
				source: this.track.id,
				id: this.track.id,
				type: 'fill-extrusion',
				paint: {
					'fill-extrusion-base': 0.5,
					'fill-extrusion-opacity': 1,
					'fill-extrusion-color': ['get', 'color'],
					'fill-extrusion-height': ['get', 'height'],
				},
			})

			// to set color like this: one color per feature : many features
			this.map.addLayer({
				type: 'line',
				source: this.track.id,
				id: this.track.id,
				paint: {
					'line-color': ['get', 'color'],
					'line-width': 14,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})
			*/

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
				source: this.track.id,
				id: this.track.id,
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
