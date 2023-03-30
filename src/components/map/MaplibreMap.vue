<template>
	<div class="map-wrapper">
		<a href="https://www.maptiler.com" class="watermark">
			<img src="https://api.maptiler.com/resources/logo.svg"
				alt="MapTiler logo">
		</a>
		<div id="gpxpod-map" ref="mapContainer" />
		<div v-if="map"
			class="map-content">
			<VMarker v-if="positionMarkerEnabled && positionMarkerLngLat"
				:map="map"
				:lng-lat="positionMarkerLngLat" />
			<!-- some stuff go away when changing the style -->
			<div v-if="mapLoaded">
				<TrackSingleColor v-if="hoveredTrack"
					:track="hoveredTrack"
					:map="map"
					:border-color="lineBorderColor" />
				<PolygonFill v-if="hoveredDirectoryLatLngs"
					layer-id="hover-dir-polygon"
					:lng-lats-list="hoveredDirectoryLatLngs"
					:map="map" />
				<div v-for="t in tracksToDraw"
					:key="t.id">
					<TrackSingleColor v-if="t.colorCriteria === null || t.colorCriteria === COLOR_CRITERIAS.none.value"
						:track="t"
						:map="map"
						:border-color="lineBorderColor" />
					<TrackGradientColorSegments v-else-if="t.colorCriteria === COLOR_CRITERIAS.speed.value"
						:track="t"
						:map="map"
						:color-criteria="t.colorCriteria"
						:border-color="lineBorderColor" />
					<TrackGradientColorPoints v-else
						:track="t"
						:map="map"
						:color-criteria="t.colorCriteria"
						:border-color="lineBorderColor" />
				</div>
				<MarkerCluster v-if="settings.show_marker_cluster === '1'"
					:map="map"
					:tracks="clusterTracks"
					:circle-border-color="lineBorderColor"
					@track-marker-hover-in="$emit('track-marker-hover-in', $event)"
					@track-marker-hover-out="$emit('track-marker-hover-out', $event)" />
				<!-- TODO add dedicated setting -->
				<PictureCluster v-if="settings.show_picture_cluster === '1'"
					:map="map"
					:pictures="clusterPictures"
					:circle-border-color="lineBorderColor"
					@picture-hover-in="$emit('picture-hover-in', $event)"
					@picture-hover-out="$emit('picture-marker-hover-out', $event)" />
			</div>
		</div>
	</div>
</template>

<script>
import { Map, NavigationControl, ScaleControl, GeolocateControl, Popup, TerrainControl, FullscreenControl } from 'maplibre-gl'
import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder'
import '@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import { imagePath } from '@nextcloud/router'

import {
	getRasterTileServers,
	getVectorStyles,
} from '../../tileServers.js'
import { kmphToSpeed, metersToElevation, minPerKmToPace } from '../../utils.js'
import { MousePositionControl, TileControl } from '../../mapControls.js'

import VMarker from './VMarker.vue'
import TrackSingleColor from './TrackSingleColor.vue'
import MarkerCluster from './MarkerCluster.vue'
import PictureCluster from './PictureCluster.vue'
import TrackGradientColorSegments from './TrackGradientColorSegments.vue'
import TrackGradientColorPoints from './TrackGradientColorPoints.vue'
import PolygonFill from './PolygonFill.vue'

import { COLOR_CRITERIAS } from '../../constants.js'
const DEFAULT_MAP_MAX_ZOOM = 22
const mapImages = {
	// marker: 'marker.png',
	// pin: 'mapIcons/pinblue.png',
}
const mapVectorImages = {
	marker: 'mapIcons/marker.svg',
	// pin: 'mapIcons/pin.svg',
	pin2: 'mapIcons/pin2.svg',
}

export default {
	name: 'MaplibreMap',

	components: {
		PictureCluster,
		PolygonFill,
		TrackSingleColor,
		TrackGradientColorPoints,
		TrackGradientColorSegments,
		MarkerCluster,
		VMarker,
	},

	props: {
		settings: {
			type: Object,
			default: () => ({}),
		},
		showMousePositionControl: {
			type: Boolean,
			default: false,
		},
		directories: {
			type: Object,
			required: true,
		},
		tracksToDraw: {
			type: Array,
			required: true,
		},
		hoveredTrack: {
			type: Object,
			default: null,
		},
		hoveredDirectoryBounds: {
			type: Object,
			default: null,
		},
		clusterTracks: {
			type: Array,
			required: true,
		},
		clusterPictures: {
			type: Array,
			required: true,
		},
		unit: {
			type: String,
			default: 'metric',
		},
	},

	data() {
		return {
			map: null,
			styles: {},
			mapLoaded: false,
			COLOR_CRITERIAS,
			mousePositionControl: null,
			scaleControl: null,
			terrainControl: null,
			persistentPopups: [],
			nonPersistentPopup: null,
			positionMarkerEnabled: false,
			positionMarkerLngLat: null,
		}
	},

	computed: {
		lineBorderColor() {
			// for testing reactivity in <Tracks*> because layers are actually re-rendered when the map style changes
			// return this.showMousePositionControl
			return ['dark', 'satellite'].includes(this.settings.mapStyle)
				? 'white'
				: 'black'
		},
		hoveredDirectoryLatLngs() {
			if (this.hoveredDirectoryBounds === null) {
				return null
			}
			const b = this.hoveredDirectoryBounds
			return [
				[[b.west, b.north], [b.east, b.north], [b.east, b.south], [b.west, b.south]],
			]
		},
	},

	watch: {
		showMousePositionControl(newValue) {
			if (newValue) {
				this.map.addControl(this.mousePositionControl, 'bottom-left')
			} else {
				this.map.removeControl(this.mousePositionControl)
			}
		},
		unit(newValue) {
			this.scaleControl?.setUnit(newValue)
		},
	},

	mounted() {
		this.initMap()
	},

	destroyed() {
		this.map.remove()
		unsubscribe('nav-toggled', this.onNavToggled)
		unsubscribe('sidebar-toggled', this.onNavToggled)
		unsubscribe('zoom-on', this.onZoomOn)
		unsubscribe('chart-point-hover', this.onChartPointHover)
		unsubscribe('chart-mouseout', this.clearChartPopups)
		unsubscribe('chart-mouseenter', this.showPositionMarker)
	},

	methods: {
		initMap() {
			const apiKey = this.settings.maptiler_api_key
			// tile servers and styles
			this.styles = {
				...getVectorStyles(apiKey),
				...getRasterTileServers(apiKey),
			}
			const restoredStyleKey = Object.keys(this.styles).includes(this.settings.mapStyle) ? this.settings.mapStyle : 'streets'
			const restoredStyleObj = this.styles[restoredStyleKey]

			// values that are saved in private page
			const centerLngLat = (this.settings.centerLat !== undefined && this.settings.centerLng !== undefined)
				? [parseFloat(this.settings.centerLng), parseFloat(this.settings.centerLat)]
				: [0, 0]
			const mapOptions = {
				container: 'gpxpod-map',
				style: restoredStyleObj.uri ? restoredStyleObj.uri : restoredStyleObj,
				center: centerLngLat,
				zoom: this.settings.zoom ?? 1,
				pitch: this.settings.pitch ?? 0,
				bearing: this.settings.bearing ?? 0,
				maxPitch: 75,
				maxZoom: restoredStyleObj.maxzoom ? (restoredStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM,
			}
			this.map = new Map(mapOptions)
			// this is set when loading public pages
			if (this.settings.initialBounds) {
				const nsew = this.settings.initialBounds
				this.map.fitBounds([[nsew.west, nsew.north], [nsew.east, nsew.south]], {
					padding: 50,
					maxZoom: 18,
					animate: false,
				})
			}
			const navigationControl = new NavigationControl({ visualizePitch: true })
			this.scaleControl = new ScaleControl({ unit: this.unit })
			if (this.settings.mapbox_api_key) {
				const geocoderControl = new MapboxGeocoder({
					accessToken: this.settings.mapbox_api_key,
					// eslint-disable-next-line
					// mapboxgl: maplibregl,
					// we don't really care if a marker is not added when searching
					mapboxgl: null,
				})
				this.map.addControl(geocoderControl, 'top-left')
			}
			const geolocateControl = new GeolocateControl({
				trackUserLocation: true,
				positionOptions: {
					enableHighAccuracy: true,
					timeout: 10000,
				},
			})
			this.map.addControl(navigationControl, 'bottom-right')
			this.map.addControl(this.scaleControl, 'top-left')
			this.map.addControl(geolocateControl, 'top-left')

			// mouse position
			this.mousePositionControl = new MousePositionControl()
			if (this.showMousePositionControl) {
				this.map.addControl(this.mousePositionControl, 'bottom-left')
			}

			// custom tile control
			const tileControl = new TileControl({ styles: this.styles, selectedKey: restoredStyleKey })
			tileControl.on('changeStyle', (key) => {
				this.$emit('map-state-change', { mapStyle: key })
				const mapStyleObj = this.styles[key]
				this.map.setMaxZoom(mapStyleObj.maxzoom ? (mapStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM)

				// if we change the tile/style provider => redraw layers
				this.reRenderLayersAndTerrain()
			})
			this.map.addControl(tileControl, 'top-right')

			const fullscreenControl = new FullscreenControl()
			this.map.addControl(fullscreenControl, 'top-right')

			// terrain
			this.terrainControl = new TerrainControl({
				source: 'terrain',
				exaggeration: 2.5,
			})
			this.map.addControl(this.terrainControl, 'top-right')
			this.terrainControl._terrainButton.addEventListener('click', (e) => {
				this.onTerrainControlClick()
			})

			this.handleMapEvents()

			this.map.on('load', () => {
				this.loadImages()

				const bounds = this.map.getBounds()
				this.$emit('map-bounds-change', {
					north: bounds.getNorth(),
					east: bounds.getEast(),
					south: bounds.getSouth(),
					west: bounds.getWest(),
				})
				this.addTerrainSource()
				if (this.settings.use_terrain === '1') {
					this.terrainControl._toggleTerrain()
				}
			})

			subscribe('nav-toggled', this.onNavToggled)
			subscribe('sidebar-toggled', this.onNavToggled)
			subscribe('zoom-on', this.onZoomOn)
			subscribe('chart-point-hover', this.onChartPointHover)
			subscribe('chart-mouseout', this.clearChartPopups)
			subscribe('chart-mouseenter', this.showPositionMarker)
		},
		loadImages() {
			// this is needed when switching between vector and raster tile servers, the image is sometimes not removed
			for (const imgKey in mapImages) {
				if (this.map.hasImage(imgKey)) {
					this.map.removeImage(imgKey)
				}
			}
			const loadImagePromises = Object.keys(mapImages).map((k) => {
				return this.loadImage(k)
			})
			loadImagePromises.push(...Object.keys(mapVectorImages).map((k) => {
				return this.loadVectorImage(k)
			}))
			Promise.allSettled(loadImagePromises)
				.then((promises) => {
					// tracks are waiting for that to load
					this.mapLoaded = true
				})
		},
		loadImage(imgKey) {
			return new Promise((resolve, reject) => {
				this.map.loadImage(
					imagePath('gpxpod', mapImages[imgKey]),
					(error, image) => {
						if (error) {
							console.error(error)
						} else {
							try {
								this.map.addImage(imgKey, image)
							} catch (e) {
							}
						}
						resolve()
					}
				)
			})
		},
		loadVectorImage(imgKey) {
			return new Promise((resolve, reject) => {
				const svgIcon = new Image(41, 41)
				svgIcon.onload = () => {
					this.map.addImage(imgKey, svgIcon)
					resolve()
				}
				svgIcon.src = imagePath('gpxpod', mapVectorImages[imgKey])
			})
		},
		reRenderLayersAndTerrain() {
			// re render the layers
			this.mapLoaded = false
			setTimeout(() => {
				this.$nextTick(() => {
					this.loadImages()
				})
			}, 500)

			setTimeout(() => {
				this.$nextTick(() => {
					this.addTerrainSource()
					if (this.settings.use_terrain === '1') {
						this.terrainControl._toggleTerrain()
					}
				})
			}, 500)
		},
		addTerrainSource() {
			const apiKey = this.settings.maptiler_api_key
			this.map.addSource('terrain', {
				type: 'raster-dem',
				url: 'https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=' + apiKey,
			})
		},
		onTerrainControlClick() {
			const enabled = this.terrainControl._terrainButton.classList.contains('maplibregl-ctrl-terrain-enabled')
			this.$emit('save-options', { use_terrain: enabled ? '1' : '0' })
		},
		handleMapEvents() {
			this.map.on('moveend', () => {
				const { lng, lat } = this.map.getCenter()
				this.$emit('map-state-change', {
					centerLng: lng,
					centerLat: lat,
					zoom: this.map.getZoom(),
					pitch: this.map.getPitch(),
					bearing: this.map.getBearing(),
				})
				const bounds = this.map.getBounds()
				this.$emit('map-bounds-change', {
					north: bounds.getNorth(),
					east: bounds.getEast(),
					south: bounds.getSouth(),
					west: bounds.getWest(),
				})
			})
		},
		// it might be a bug in maplibre: when navigation sidebar is toggled, the map fails to resize
		// and an empty area appears on the right
		// this fixes it
		onNavToggled() {
			setTimeout(() => {
				this.$nextTick(() => this.map.resize())
			}, 300)

			this.clearChartPopups({ keepPersistent: false })
		},
		onZoomOn(nsew) {
			if (this.map) {
				this.map.fitBounds([[nsew.west, nsew.north], [nsew.east, nsew.south]], {
					padding: 50,
					maxZoom: 18,
				})
			}
		},
		onChartPointHover({ point, persist }) {
			// center on hovered point
			if (this.settings.follow_chart_hover === '1') {
				this.map.setCenter([point[0], point[1]])
				// flyTo movement is still ongoing when showing non-persistent popups so they disapear...
				// this.map.flyTo({ center: [lng, lat] })
			}

			// if this is a hover (and not a click) and we don't wanna show popups: show a marker
			if (!persist && this.settings.chart_hover_show_detailed_popup !== '1') {
				this.positionMarkerLngLat = [point[0], point[1]]
			} else {
				this.addPopup(point, persist)
			}
		},
		addPopup(point, persist) {
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
			const containerClass = persist ? 'class="with-button"' : ''
			const dataHtml = (point[3] === null && point[2] === null)
				? t('gpxpod', 'No data')
				: (point[3] !== null ? ('<strong>' + t('gpxpod', 'Date') + '</strong>: ' + moment.unix(point[3]).format('YYYY-MM-DD HH:mm:ss (Z)') + '<br>') : '')
				+ (point[2] !== null ? ('<strong>' + t('gpxpod', 'Altitude') + '</strong>: ' + metersToElevation(point[2]) + '<br>') : '')
				+ (point[4] !== null ? ('<strong>' + t('gpxpod', 'Speed') + '</strong>: ' + kmphToSpeed(point[4]) + '<br>') : '')
				+ (point[5] !== null ? ('<strong>' + t('gpxpod', 'Pace') + '</strong>: ' + minPerKmToPace(point[5])) : '')
			const html = '<div ' + containerClass + ' style="border-color: ' + point[6] + ';">'
				+ dataHtml
				+ '</div>'
			const popup = new Popup({
				closeButton: persist,
				closeOnClick: !persist,
				closeOnMove: !persist,
			})
				.setLngLat([point[0], point[1]])
				.setHTML(html)
				.addTo(this.map)
			if (persist) {
				this.persistentPopups.push(popup)
			} else {
				this.nonPersistentPopup = popup
			}
		},
		clearChartPopups({ keepPersistent }) {
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
			if (!keepPersistent) {
				this.persistentPopups.forEach(p => {
					p.remove()
				})
				this.persistentPopups = []
			}
			this.positionMarkerEnabled = false
			this.positionMarkerLngLat = null
		},
		showPositionMarker() {
			this.positionMarkerEnabled = true
		},
	},
}
</script>

<style scoped lang="scss">
@import '~maplibre-gl/dist/maplibre-gl.css';

.map-wrapper {
	position: relative;
	width: 100%;
	height: 100%;
	//height: calc(100vh - 77px); /* calculate height of the screen minus the heading */

	#gpxpod-map {
		width: 100%;
		height: 100%;
	}

	.watermark {
		position: absolute;
		left: 10px;
		bottom: 18px;
		z-index: 999;
	}
}
</style>
