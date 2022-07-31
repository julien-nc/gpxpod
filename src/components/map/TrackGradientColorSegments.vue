<script>
import { COLOR_CRITERIAS, getColorHueInInterval } from '../../constants'
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor'
import { LngLat } from 'maplibre-gl'

/**
 * Generates one layer in which there is one segment per point pair
 * Each segment is colored according to the selected criteria (speed or pace at the moment)
 * For the elevation criteria, it's more realistic to assign colors to points and use a gradient
 * for each segment.
 */
export default {
	name: 'TrackGradientColorSegments',

	components: {
	},

	mixins: [WatchLineBorderColor],

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
			default: COLOR_CRITERIAS.speed.value,
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
		getSegmentValue() {
			return this.colorCriteria === COLOR_CRITERIAS.speed.value
				? this.getSpeed
				: () => 0
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
		console.debug('[gpxpod] destroy track', this.stringId)
		this.remove()
	},

	methods: {
		getFeaturesFromCoords(coords) {
			if (coords.length < 2) {
				return [this.buildFeature(coords, this.color)]
			} else {
				const { min, max, segmentValues } = this.getMinMaxAndValues(coords)
				const features = []
				// for each consecutive 2 points
				for (let fi = 0; fi < coords.length - 1; fi++) {
					features.push(this.buildFeature([coords[fi], coords[fi + 1]], this.getColor(min, max, segmentValues[fi])))
				}
				return features
			}
		},
		getMinMaxAndValues(coords) {
			const lngLats = coords.map((c) => new LngLat(c[0], c[1]))
			const segmentValues = [this.getSegmentValue(lngLats[0], lngLats[1], coords[0], coords[1])]
			let min = segmentValues[0]
			let max = segmentValues[0]

			for (let i = 1; i < coords.length - 1; i++) {
				segmentValues.push(this.getSegmentValue(lngLats[i], lngLats[i + 1], coords[i], coords[i + 1]))
				if (segmentValues[i]) {
					if (segmentValues[i] > max) max = segmentValues[i]
					if (segmentValues[i] < min) min = segmentValues[i]
				}
			}
			return { min, max, segmentValues }
		},
		getSpeed(ll1, ll2, coord1, coord2) {
			const distance = ll1.distanceTo(ll2)
			const time = coord2[3] - coord1[3]
			return distance / time
		},
		getColor(min, max, value) {
			const weight = (value - min) / (max - min)
			const hue = getColorHueInInterval(240, 0, weight)
			return 'hsl(' + hue + ', 100%, 50%)'
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
