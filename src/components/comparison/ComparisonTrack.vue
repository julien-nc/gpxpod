<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import BringTrackToTop from '../../mixins/BringTrackToTop.js'

export default {
	name: 'ComparisonTrack',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		BringTrackToTop,
	],

	props: {
		geojson: {
			type: Object,
			required: true,
		},
		comparisonCriteria: {
			type: String,
			default: 'time',
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
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			ready: false,
			onTop: false,
		}
	},

	computed: {
		layerId() {
			return String(this.geojson.id)
		},
		borderLayerId() {
			return this.layerId + '-border'
		},
		invisibleBorderLayerId() {
			return this.layerId + '-invisible-border'
		},
		color() {
			return '#0693e3'
		},
		processedGeojsonData() {
			console.debug('[gpxpod] compute comparison geojson', this.geojson)
			// use short point list for hovered track when we don't have the data yet
			const result = {
				type: 'FeatureCollection',
				features: this.geojson.features.map(f => {
					return {
						...f,
						properties: {
							...f.properties,
							color: this.getFeatureColor(f, this.comparisonCriteria),
						},
					}
				}),
			}
			return result
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
		processedGeojsonData() {
			console.debug('[gpxpod] processedGeojsonData has changed')
			this.remove()
			this.init()
		},
	},

	mounted() {
		console.debug('[gpxpod] comparison track mounted!!!!!', String(this.geojson.id))
		this.init()
	},

	destroyed() {
		console.debug('[gpxpod] destroy comparison track', String(this.geojson.id))
		this.remove()
	},

	methods: {
		getFeatureColor(feature, criteria) {
			const props = feature.properties
			if (criteria === 'time') {
				if (props.quickerThan.length > 0) {
					return 'lightgreen'
				} else if (props.slowerThan.length > 0) {
					return 'red'
				}
			} else if (criteria === 'distance') {
				if (props.shorterThan.length > 0) {
					return 'lightgreen'
				} else if (props.longerThan.length > 0) {
					return 'red'
				}
			} else if (criteria === 'elevation') {
				if (props.lessPositiveDenivThan.length > 0) {
					return 'lightgreen'
				} else if (props.morePositiveDenivThan.length > 0) {
					return 'red'
				}
			}
			return this.color
		},
		bringToTop() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}
			if (this.map.getLayer(this.layerId)) {
				this.map.moveLayer(this.layerId)
			}
		},
		onMouseEnter() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth * 1.7)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', (this.lineWidth * 1.6) * 1.7)
			}
		},
		onMouseLeave() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 1.6)
			}
		},
		remove() {
			if (this.map.getLayer(this.layerId)) {
				this.map.removeLayer(this.layerId)
				this.map.removeLayer(this.borderLayerId)
				this.map.removeLayer(this.invisibleBorderLayerId)
			}
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},
		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.processedGeojsonData,
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
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.layerId,
				paint: {
					'line-color': ['get', 'color'],
					'line-width': this.lineWidth,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
				filter: ['!=', '$type', 'Point'],
			})

			this.ready = true
		},
	},
	render(h) {
		return null
	},
}
</script>
