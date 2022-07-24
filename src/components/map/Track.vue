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
		this.remove()
	},

	methods: {
		remove() {
			this.map.removeLayer(this.track.id)
			this.map.removeSource(this.track.id)
		},
		init() {
			this.map.addSource(this.track.id, {
				type: 'geojson',
				lineMetrics: true,
				data: this.track.geojson,
			})
			/*
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
			*/

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
