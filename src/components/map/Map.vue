<template>
	<div class="map-wrapper">
		<a href="https://www.maptiler.com" class="watermark">
			<img src="https://api.maptiler.com/resources/logo.svg"
				alt="MapTiler logo">
		</a>
		<div id="gpxpod-map" ref="mapContainer" />
		<div v-if="map"
			class="map-content">
			<VMarker :map="map"
				:lng-lat="[-123.9749, 40.7736]" />
			<!-- some stuff go away when changing the style -->
			<div v-if="mapLoaded">
				<Track v-if="hoveredTrack"
					:track="hoveredTrack"
					:map="map"
					:border-color="lineBorderColor" />
				<div v-for="t in tracksToDraw"
					:key="t.id">
					<Track v-if="t.colorCriteria === null || t.colorCriteria === COLOR_CRITERIAS.none.value"
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
				<MarkerCluster :map="map"
					:tracks="clusterTracks"
					:circle-border-color="lineBorderColor"
					@track-marker-hover-in="$emit('track-marker-hover-in', $event)"
					@track-marker-hover-out="$emit('track-marker-hover-out', $event)" />
			</div>
		</div>
	</div>
</template>

<script>
import { Map, NavigationControl, ScaleControl } from 'maplibre-gl'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import {
	getRasterTileServers,
	getVectorStyles,
	MyCustomControl,
} from '../../tileServers'
import { MousePositionControl } from '../../utils'

import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder'
import '@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css'

import VMarker from './VMarker'
import Track from './Track'
import MarkerCluster from './MarkerCluster'
import TrackGradientColorSegments from './TrackGradientColorSegments'
import TrackGradientColorPoints from './TrackGradientColorPoints'

import { COLOR_CRITERIAS } from '../../constants'
const DEFAULT_MAP_MAX_ZOOM = 22

export default {
	name: 'Map',

	components: {
		TrackGradientColorPoints,
		TrackGradientColorSegments,
		MarkerCluster,
		Track,
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
			mapLoaded: false,
			COLOR_CRITERIAS,
			mousePositionControl: null,
			scaleControl: null,
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
	},

	methods: {
		initMap() {
			const apiKey = this.settings.maptiler_api_key
			// tile servers and styles
			const styles = {
				...getVectorStyles(apiKey),
				...getRasterTileServers(apiKey),
			}
			const restoredStyleKey = Object.keys(styles).includes(this.settings.mapStyle) ? this.settings.mapStyle : 'streets'
			const restoredStyleObj = styles[restoredStyleKey]

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
			// eslint-disable-next-line
			const map = this.settings.maplibre_beta ? new maplibregl.Map(mapOptions) : new Map(mapOptions)
			const navigationControl = this.settings.maplibre_beta
				// eslint-disable-next-line
				? new maplibregl.NavigationControl({ visualizePitch: true })
				: new NavigationControl({ visualizePitch: true })
			this.scaleControl = this.settings.maplibre_beta
				// eslint-disable-next-line
				? new maplibregl.ScaleControl({ unit: this.unit })
				: new ScaleControl({ unit: this.unit })
			if (this.settings.mapbox_api_key) {
				const geocoderControl = new MapboxGeocoder({
					accessToken: this.settings.mapbox_api_key,
					// eslint-disable-next-line
					// mapboxgl: this.settings.maplibre_beta ? maplibregl : null,
					// we don't really care if a marker is not added when searching
					mapboxgl: null,
				})
				map.addControl(geocoderControl, 'top-left')
			}
			map.addControl(navigationControl, 'bottom-right')
			map.addControl(this.scaleControl, 'top-left')

			// mouse position
			this.mousePositionControl = new MousePositionControl()
			if (this.showMousePositionControl) {
				map.addControl(this.mousePositionControl, 'bottom-left')
			}

			// custom tile control
			const myTileControl = new MyCustomControl({ styles, selectedKey: restoredStyleKey })
			myTileControl.on('changeStyle', (key) => {
				this.$emit('map-state-change', { mapStyle: key })
				const mapStyleObj = styles[key]
				map.setMaxZoom(mapStyleObj.maxzoom ? (mapStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM)

				// if we change the tile/style provider => redraw layers
				this.reRenderLayersAndTerrain()
			})
			map.addControl(myTileControl, 'top-right')

			this.handleMapEvents(map)

			this.map = map
			map.on('load', () => {
				// tracks are waiting for that to load
				this.mapLoaded = true
				const bounds = map.getBounds()
				this.$emit('map-bounds-change', {
					north: bounds.getNorth(),
					east: bounds.getEast(),
					south: bounds.getSouth(),
					west: bounds.getWest(),
				})
			})
			/*
			// we can't do that because this event is triggered on map.addImage()
			// when the style changes, we loose the layers and the terrain
			map.on('styledata', (e) => {
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
			setTimeout(() => {
				this.$nextTick(() => {
					this.addTerrain()
				})
			}, 500)
		},
		addTerrain() {
			console.debug('add terrain')
			if (!this.settings.maplibre_beta) {
				return
			}
			if (this.map.getSource('terrain')) {
				this.map.removeSource('terrain')
			}

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
		handleMapEvents(map) {
			map.on('moveend', () => {
				const { lng, lat } = map.getCenter()
				this.$emit('map-state-change', {
					centerLng: lng,
					centerLat: lat,
					zoom: map.getZoom(),
					pitch: map.getPitch(),
					bearing: map.getBearing(),
				})
				const bounds = map.getBounds()
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
			}, 100)
		},
		onZoomOn(nsew) {
			if (this.map) {
				this.map.fitBounds([[nsew.west, nsew.north], [nsew.east, nsew.south]], {
					padding: 50,
					maxZoom: 18,
				})
			}
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
