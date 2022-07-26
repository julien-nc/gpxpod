<script>
import { Popup } from 'maplibre-gl'

const LAYER_SUFFIXES = ['clusters', 'cluster-count', 'unclustered-point']

export default {
	name: 'MarkerCluster',

	components: {
	},

	mixins: [],

	props: {
		tracks: {
			type: Array,
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
			stringId: 'cluster',
		}
	},

	computed: {
		clusterGeojsonData() {
			const features = this.tracks.map((track) => {
				return {
					type: 'Feature',
					properties: {
						name: track.name,
						total_distance: track.total_distance,
					},
					geometry: {
						type: 'Point',
						coordinates: [track.lon, track.lat],
					},
				}
			})
			const geojson = {
				type: 'FeatureCollection',
				features,
			}
			return geojson
		},
	},

	watch: {
		clusterGeojsonData(n) {
			console.debug('CLUSTER tracks changed', n)
			this.remove()
			this.init()
		},
	},

	mounted() {
		this.init()
	},

	destroyed() {
		this.remove()
	},

	methods: {
		remove() {
			LAYER_SUFFIXES.forEach((s) => {
				if (this.map.getLayer(this.stringId + s)) {
					this.map.removeLayer(this.stringId + s)
				}
			})
			if (this.map.getSource(this.stringId)) {
				this.map.removeSource(this.stringId)
			}
		},
		init() {
			this.map.addSource(this.stringId, {
				type: 'geojson',
				data: this.clusterGeojsonData,
				cluster: true,
				clusterMaxZoom: 14,
				clusterRadius: 50,
			})

			this.map.addLayer({
				id: this.stringId + 'clusters',
				type: 'circle',
				source: this.stringId,
				filter: ['has', 'point_count'],
				paint: {
					'circle-color': [
						'step',
						['get', 'point_count'],
						'#51bbd6',
						100,
						'#f1f075',
						750,
						'#f28cb1',
					],
					'circle-radius': [
						'step',
						['get', 'point_count'],
						20,
						100,
						30,
						750,
						40,
					],
				},
			})

			this.map.addLayer({
				id: this.stringId + 'cluster-count',
				type: 'symbol',
				source: this.stringId,
				filter: ['has', 'point_count'],
				layout: {
					'text-field': '{point_count_abbreviated}',
					'text-font': ['DIN Offc Pro Medium', 'Arial Unicode MS Bold'],
					'text-size': 12,
				},
			})

			this.map.addLayer({
				id: this.stringId + 'unclustered-point',
				type: 'circle',
				source: this.stringId,
				filter: ['!', ['has', 'point_count']],
				paint: {
					'circle-color': '#11b4da',
					'circle-radius': 8,
					'circle-stroke-width': 1,
					'circle-stroke-color': '#fff',
				},
			})

			this.map.on('click', this.stringId + 'clusters', (e) => {
				const features = this.map.queryRenderedFeatures(e.point, {
					layers: [this.stringId + 'clusters'],
				})
				const clusterId = features[0].properties.cluster_id
				this.map.getSource(this.stringId).getClusterExpansionZoom(
					clusterId,
					(err, zoom) => {
						if (err) {
							return
						}

						this.map.easeTo({
							center: features[0].geometry.coordinates,
							zoom,
						})
					},
				)
			})

			this.map.on('click', this.stringId + 'unclustered-point', (e) => {
				const coordinates = e.features[0].geometry.coordinates.slice()
				const p = e.features[0].properties

				// Ensure that if the map is zoomed out such that
				// multiple copies of the feature are visible, the
				// popup appears over the copy being pointed to.
				while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
					coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360
				}

				new Popup()
					.setLngLat(coordinates)
					.setHTML(
						'Name: ' + p.name + '<br>'
						+ 'dist: ' + p.total_distance
					)
					.addTo(this.map)
			})

			this.map.on('mouseenter', this.stringId + 'clusters', () => {
				this.map.getCanvas().style.cursor = 'pointer'
			})
			this.map.on('mouseleave', this.stringId + 'clusters', () => {
				this.map.getCanvas().style.cursor = ''
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
