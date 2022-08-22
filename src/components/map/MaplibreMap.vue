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
			</div>
		</div>
	</div>
</template>

<script>
import { Map, NavigationControl, ScaleControl, GeolocateControl, Popup } from 'maplibre-gl'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import {
	getRasterTileServers,
	getVectorStyles,
	MyTileControl,
} from '../../tileServers.js'
import { kmphToSpeed, metersToElevation, MousePositionControl } from '../../utils.js'

import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder'
import '@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css'

import VMarker from './VMarker.vue'
import TrackSingleColor from './TrackSingleColor.vue'
import MarkerCluster from './MarkerCluster.vue'
import TrackGradientColorSegments from './TrackGradientColorSegments.vue'
import TrackGradientColorPoints from './TrackGradientColorPoints.vue'
import PolygonFill from './PolygonFill.vue'

import { COLOR_CRITERIAS } from '../../constants.js'
const DEFAULT_MAP_MAX_ZOOM = 22

export default {
	name: 'MaplibreMap',

	components: {
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
		useTerrain: {
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
		useTerrain(newValue) {
			console.debug('change use_terrain', newValue)

			if (newValue) {
				this.addTerrain()
			} else {
				this.removeTerrain()
			}
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
			const myTileControl = new MyTileControl({ styles: this.styles, selectedKey: restoredStyleKey })
			myTileControl.on('changeStyle', (key) => {
				this.$emit('map-state-change', { mapStyle: key })
				const mapStyleObj = this.styles[key]
				this.map.setMaxZoom(mapStyleObj.maxzoom ? (mapStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM)

				// if we change the tile/style provider => redraw layers
				this.reRenderLayersAndTerrain()
			})
			this.map.addControl(myTileControl, 'top-right')

			this.handleMapEvents()

			this.map.on('load', () => {
				// tracks are waiting for that to load
				this.mapLoaded = true
				const bounds = this.map.getBounds()
				this.$emit('map-bounds-change', {
					north: bounds.getNorth(),
					east: bounds.getEast(),
					south: bounds.getSouth(),
					west: bounds.getWest(),
				})
				if (this.useTerrain) {
					this.addTerrain()
				}
			})
			/*
			// we can't do that because this event is triggered on map.addImage()
			// when the style changes, we loose the layers and the terrain
			this.map.on('styledata', (e) => {
				if (e.style?._changed) {
					console.debug('styledata changed', e)
					console.debug('[gpxpod] A styledata event occurred with _changed === true -> rerender layers and add terrain')
					this.reRenderLayersAndTerrain()
				}
			})
			*/

			subscribe('nav-toggled', this.onNavToggled)
			subscribe('sidebar-toggled', this.onNavToggled)
			subscribe('zoom-on', this.onZoomOn)
			subscribe('chart-point-hover', this.onChartPointHover)
			subscribe('chart-mouseout', this.clearChartPopups)
			subscribe('chart-mouseenter', this.showPositionMarker)
		},
		reRenderLayersAndTerrain() {
			// re render the layers
			this.mapLoaded = false
			setTimeout(() => {
				this.$nextTick(() => {
					this.mapLoaded = true
				})
			}, 500)

			// add the terrain
			if (this.useTerrain) {
				setTimeout(() => {
					this.$nextTick(() => {
						this.addTerrain()
					})
				}, 500)
			}
		},
		removeTerrain() {
			console.debug('[gpxpod] remove terrain')
			if (this.map.getSource('terrain')) {
				this.map.removeSource('terrain')
			}
		},
		addTerrain() {
			this.removeTerrain()
			console.debug('[gpxpod] add terrain')

			const apiKey = this.settings.maptiler_api_key
			// terrain for maplibre >= 2.2.0
			this.map.addSource('terrain', {
				type: 'raster-dem',
				url: 'https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=' + apiKey,
			})
			this.map.setTerrain({
				source: 'terrain',
				exaggeration: 2.5,
			})
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
				+ (point[4] !== null ? ('<strong>' + t('gpxpod', 'Speed') + '</strong>: ' + kmphToSpeed(point[4])) : '')
			const html = '<div ' + containerClass + ' style="border-color: ' + point[5] + ';">'
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
	//position: relative;
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
		bottom: 10px;
		z-index: 999;
	}
}
</style>
