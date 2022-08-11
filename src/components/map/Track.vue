<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor'
import PointInfoPopup from '../../mixins/PointInfoPopup'
import BringTrackToTop from '../../mixins/BringTrackToTop'

export default {
	name: 'Track',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		PointInfoPopup,
		BringTrackToTop,
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
			console.debug('watch trackGeojsonData')
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
		bringToTop() {
			if (this.map.getLayer(this.layerId) && this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
				this.map.moveLayer(this.layerId)
			}
		},
		remove() {
			if (this.map.getLayer(this.layerId)) {
				this.map.removeLayer(this.layerId)
				this.map.removeLayer(this.borderLayerId)
			}
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
				id: this.borderLayerId,
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
				source: this.layerId,
				id: this.layerId,
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
