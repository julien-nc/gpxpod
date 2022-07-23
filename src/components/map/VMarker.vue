<script>
import Options from '../../mixins/Options'
import { Marker } from 'maplibre-gl'

export default {
	name: 'VMarker',

	components: {
	},

	mixins: [Options],

	props: {
		lngLat: {
			type: [Object, Array],
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
			mapObject: null,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		this.initMarker()
	},

	destroyed() {
		this.mapObject.remove()
	},

	methods: {
		initMarker() {
			this.mapObject = new Marker(this.options)
				.setLngLat(this.lngLat)
				.addTo(this.map)
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
