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
					:map="map" />
				<div v-for="t in tracksToDraw"
					:key="t.id">
					<Track v-if="t.color_criteria === null"
						:track="t"
						:map="map" />
					<TrackGradient v-else
						:track="t"
						:map="map" />
				</div>
				<MarkerCluster :map="map"
					:tracks="clusterTracks" />
			</div>
		</div>
	</div>
</template>

<script>
import { Map, NavigationControl, ScaleControl } from 'maplibre-gl'
import { MapboxStyleSwitcherControl } from 'mapbox-gl-style-switcher'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import {
	// getRasterTileServers,
	getVectorStyles,
} from '../../tileServers'

import 'mapbox-gl-style-switcher/styles.css'
import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder'
import '@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css'

import VMarker from './VMarker'
import Track from './Track'
import MarkerCluster from './MarkerCluster'
import TrackGradient from './TrackGradient'

export default {
	name: 'Map',

	components: {
		TrackGradient,
		MarkerCluster,
		Track,
		VMarker,
	},

	props: {
		settings: {
			type: Object,
			default: () => ({}),
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
	},

	data() {
		return {
			map: null,
			mapLoaded: false,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		this.initMap()
	},

	destroyed() {
		this.map.remove()
		unsubscribe('nav-toggled', this.onNavToggled)
	},

	methods: {
		initMap() {
			const apiKey = this.settings.maptiler_api_key
			// tile servers and styles
			const vectorStyles = getVectorStyles(apiKey)
			const vectorStylesList = Object.values(vectorStyles)
			const restoredStyleObj = vectorStylesList.find((s) => s.title === this.settings.mapStyle)
			const restoredStyleUri = restoredStyleObj?.uri ?? vectorStyles.streets.uri

			const centerLngLat = (this.settings.centerLat !== undefined && this.settings.centerLng !== undefined)
				? [parseFloat(this.settings.centerLng), parseFloat(this.settings.centerLat)]
				: [0, 0]
			const mapOptions = {
				container: 'gpxpod-map',
				style: restoredStyleUri,
				// style: getRasterTileServers(apiKey).osm,
				center: centerLngLat,
				zoom: this.settings.zoom ?? 1,
				pitch: this.settings.pitch ?? 0,
				bearing: this.settings.bearing ?? 0,
				maxPitch: 75,
			}
			// eslint-disable-next-line
			const map = this.settings.maplibre_beta ? new maplibregl.Map(mapOptions) : new Map(mapOptions)
			const navigationControl = this.settings.maplibre_beta
				// eslint-disable-next-line
				? new maplibregl.NavigationControl({ visualizePitch: true })
				: new NavigationControl({ visualizePitch: true })
			const scaleControl = this.settings.maplibre_beta
				// eslint-disable-next-line
				? new maplibregl.ScaleControl()
				: new ScaleControl()
			const scaleControl2 = this.settings.maplibre_beta
				// eslint-disable-next-line
				? new maplibregl.ScaleControl({ unit: 'imperial' })
				: new ScaleControl({ unit: 'imperial' })
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
			map.addControl(scaleControl, 'top-left')
			map.addControl(scaleControl2, 'top-left')

			const options = {
				defaultStyle: this.settings.mapStyle ?? 'Streets',
				eventListeners: {
					onChange: (e, style) => {
						const styleObj = vectorStylesList.find((s) => s.uri.startsWith(style))
						if (styleObj) {
							this.$emit('map-state-change', { mapStyle: styleObj.title })
						}
					},
					// return true if you want to stop execution
					//           onOpen: (event: MouseEvent) => boolean;
					//           onSelect: (event: MouseEvent) => boolean;
					//           onChange: (event: MouseEvent, style: string) => boolean;
				},
			}
			map.addControl(new MapboxStyleSwitcherControl(vectorStylesList, options))

			this.handleMapEvents(map)

			this.map = map
			map.on('load', () => {
				// tracks are waiting for that to load
				this.mapLoaded = true
			})
			// when the style changes, we loose the layers and the terrain
			map.on('styledata', (e) => {
				if (e.style?._changed) {
					console.debug('A styledata event occurred with _changed === true -> rerender layers and add terrain')
					// re render the layers
					this.mapLoaded = false
					this.$nextTick(() => {
						this.mapLoaded = true
					})
					// add the terrain
					setTimeout(() => {
						this.$nextTick(() => {
							this.addTerrain()
						})
					}, 500)
				}
			})

			subscribe('nav-toggled', this.onNavToggled)
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
