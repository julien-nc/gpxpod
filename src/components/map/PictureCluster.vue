<script>
import { Popup, Marker } from 'maplibre-gl'
// import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'

const LAYER_SUFFIXES = {
	CLUSTERS: 'clusters',
	CLUSTERS_COUNT: 'cluster-count',
	// UNCLUSTERED_POINT: 'unclustered-point',
}

const PHOTO_MARKER_SIZE = 45

export default {
	name: 'PictureCluster',

	components: {
	},

	mixins: [],

	props: {
		pictures: {
			type: Array,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		circleBorderColor: {
			type: String,
			default: 'black',
		},
	},

	data() {
		return {
			ready: false,
			stringId: 'pictureCluster',
			hoverPopup: null,
			clickPopups: {},
			currentHoveredPicture: null,
			markers: {},
			markersOnScreen: {},
		}
	},

	computed: {
		clusterGeojsonData() {
			const features = this.pictures.map((pic) => {
				return {
					type: 'Feature',
					properties: {
						id: pic.id,
						path: pic.path,
						file_id: pic.file_id,
						date_taken: pic.date_taken,
						directory_id: pic.directory_id,
					},
					geometry: {
						type: 'Point',
						coordinates: [pic.lng, pic.lat],
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
			console.debug('CLUSTER pictures changed', n)
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
			Object.values(LAYER_SUFFIXES).forEach((s) => {
				if (this.map.getLayer(this.stringId + s)) {
					this.map.removeLayer(this.stringId + s)
				}
			})
			if (this.map.getSource(this.stringId)) {
				this.map.removeSource(this.stringId)
			}
			// release event handlers
			this.map.off('click', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterClick)
			this.map.off('mouseenter', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterMouseEnter)
			this.map.off('mouseleave', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterMouseLeave)

			this.map.off('render', this.onMapRender)
		},
		bringToTop() {
			Object.values(LAYER_SUFFIXES).forEach((s) => {
				if (this.map.getLayer(this.stringId + s)) {
					this.map.moveLayer(this.stringId + s)
				}
			})
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
				id: this.stringId + LAYER_SUFFIXES.CLUSTERS,
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
				id: this.stringId + LAYER_SUFFIXES.CLUSTERS_COUNT,
				type: 'symbol',
				source: this.stringId,
				filter: ['has', 'point_count'],
				layout: {
					'text-field': '{point_count_abbreviated}',
					'text-font': ['DIN Offc Pro Medium', 'Arial Unicode MS Bold'],
					'text-size': 12,
				},
			})

			this.map.on('click', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterClick)
			this.map.on('mouseenter', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterMouseEnter)
			this.map.on('mouseleave', this.stringId + LAYER_SUFFIXES.CLUSTERS, this.onClusterMouseLeave)

			this.map.on('render', this.onMapRender)

			this.ready = true
		},
		onMapRender(e) {
			if (this.map.isSourceLoaded(this.stringId)) {
				this.updateMarkers()
			}
		},
		updateMarkers() {
			const newMarkers = {}
			const features = this.map.querySourceFeatures(this.stringId)

			// for every cluster on the screen, create an HTML marker for it (if we didn't yet),
			// and add it to the map if it's not there already
			for (const feature of features) {
				const coords = feature.geometry.coordinates
				const picture = feature.properties
				if (picture.cluster) {
					continue
				}
				const id = picture.id

				if (!this.markers[id]) {
					const previewUrl = generateUrl('core/preview?fileId={fileId}&x=341&y=256&a=1', { fileId: picture.file_id })
					const el = this.createMarkerElement(picture, previewUrl)
					this.markers[id] = new Marker({
						element: el,
						offset: [0, -(PHOTO_MARKER_SIZE + 10) / 2],
					})
						.setLngLat(coords)
					const markerDiv = this.markers[id].getElement()
					markerDiv.addEventListener('mouseenter', () => {
						this.onUnclusteredPointMouseEnter(coords, picture)
					})
					markerDiv.addEventListener('mouseleave', () => {
						this.onUnclusteredPointMouseLeave(coords, picture)
					})
					markerDiv.addEventListener('click', () => {
						this.onUnclusteredPointClick(coords, picture)
					})
				}
				newMarkers[id] = this.markers[id]

				if (!this.markersOnScreen[id]) {
					this.markers[id].addTo(this.map)
				}
			}
			// for every marker we've added previously, remove those that are no longer visible
			for (const id in this.markersOnScreen) {
				if (!newMarkers[id]) {
					// TODO store markerDiv event listeners lambdas to be able to remove them later (here)
					this.markersOnScreen[id].remove()
				}
			}
			this.markersOnScreen = newMarkers
		},
		createMarkerElement(picture, previewUrl) {
			const mainDiv = document.createElement('div')
			mainDiv.classList.add('picture-marker')
			const innerDiv = document.createElement('div')
			mainDiv.appendChild(innerDiv)
			innerDiv.classList.add('picture-marker--content')
			innerDiv.setAttribute('style',
				'width: ' + PHOTO_MARKER_SIZE + 'px;'
				+ 'height: ' + PHOTO_MARKER_SIZE + 'px;'
				+ 'border: 2px solid var(--color-border);'
				+ 'border-radius: var(--border-radius);')
			const imgDiv = document.createElement('div')
			imgDiv.setAttribute('style', 'background-image: url(\'' + previewUrl + '\');'
				+ 'width: 100%;'
				+ 'height: 100%;'
				+ 'background-size: cover;'
				+ 'background-position: center center;'
				+ 'background-repeat: no-repeat;'
				+ 'background-color: white;'
			)
			innerDiv.appendChild(imgDiv)
			return mainDiv
		},
		getPicturePopupHtml(picture, withButton = false) {
			return '<div ' + (withButton ? 'class="with-button"' : '')
				+ 'style="border-color: cyan;">'
				+ '<strong>' + t('gpxpod', 'Name') + '</strong>: ' + picture.path
				+ '</div>'
		},
		onUnclusteredPointClick(pictureCoords, picture) {
			const coordinates = pictureCoords.slice()

			// Ensure that if the map is zoomed out such that
			// multiple copies of the feature are visible, the
			// popup appears over the copy being pointed to.
			while (Math.abs(pictureCoords[0] - coordinates[0]) > 180) {
				coordinates[0] += pictureCoords[0] > coordinates[0] ? 360 : -360
			}

			// avoid adding multiple popups for the same marker
			if (!this.clickPopups[picture.id]) {
				const html = this.getPicturePopupHtml(picture, true)
				const popup = new Popup({
					offset: [0, -(PHOTO_MARKER_SIZE + 10)],
					maxWidth: '240px',
					closeButton: true,
					closeOnClick: false,
					closeOnMove: false,
				})
					.setLngLat(coordinates)
					.setHTML(html)

				popup.on('close', () => { delete this.clickPopups[picture.id] })
				popup.addTo(this.map)
				this.clickPopups[picture.id] = popup
			}
		},
		onUnclusteredPointMouseEnter(pictureCoords, picture) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.bringToTop()

			// display a popup if there is no 'click' one for this pic
			if (!this.clickPopups[picture.id]) {
				const coordinates = pictureCoords.slice()
				const html = this.getPicturePopupHtml(picture, false)
				this.hoverPopup = new Popup({
					offset: [0, -(PHOTO_MARKER_SIZE + 10)],
					maxWidth: '240px',
					closeButton: false,
					closeOnClick: true,
					closeOnMove: true,
				})
					.setLngLat(coordinates)
					.setHTML(html)
					.addTo(this.map)
			}

			this.currentHoveredPicture = picture
			this.$emit('picture-hover-in', { pictureId: picture.id, dirId: picture.directory_id })
		},
		onUnclusteredPointMouseLeave(pictureCoords, picture) {
			this.map.getCanvas().style.cursor = ''
			this.hoverPopup?.remove()
			this.hoverPopup = null

			this.$emit('picture-hover-out', { pictureId: picture.id, dirId: picture.directory_id })
			this.currentHoveredPicture = null
		},
		onClusterClick(e) {
			const features = this.map.queryRenderedFeatures(e.point, {
				layers: [this.stringId + LAYER_SUFFIXES.CLUSTERS],
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
		},
		onClusterMouseEnter(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.bringToTop()
		},
		onClusterMouseLeave(e) {
			this.map.getCanvas().style.cursor = ''
		},
	},
	render(h) {
		return null
	},
}
</script>
