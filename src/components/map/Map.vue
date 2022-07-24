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
			<Track v-if="mapLoaded" :track="track" :map="map" />
		</div>
	</div>
</template>

<script>
import { Map, NavigationControl, ScaleControl } from 'maplibre-gl'
import { MapboxStyleSwitcherControl } from 'mapbox-gl-style-switcher'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import VMarker from './VMarker'

import 'mapbox-gl-style-switcher/styles.css'
import Track from './Track'

export default {
	name: 'Map',

	components: {
		Track,
		VMarker,
	},

	props: {
		settings: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			map: null,
			mapLoaded: false,
			scaleControl: null,
			track: {
				id: 'plop',
				geojson: {
					type: 'FeatureCollection',
					features: [
						{
							type: 'Feature',
							properties: { height: 100, color: 'blue' },
							geometry: {
								coordinates: [
									[-77.044211, 38.852924, 1],
									[-77.045659, 38.860158, 500],
									[-77.044232, 38.862326, 500],
									[-77.040879, 38.865454, 500],
									[-77.039936, 38.867698, 500],
									[-77.040338, 38.86943, 500],
									[-77.04264, 38.872528, 500],
								],
								type: 'LineString',
							},
						},
						{
							type: 'Feature',
							properties: { height: 200, color: 'red' },
							geometry: {
								coordinates: [
									[-77.04264, 38.872528, 500],
									[-77.03696, 38.878424, 1000],
									[-77.032309, 38.87937, 1000],
									[-77.030056, 38.880945, 1000],
									[-77.027645, 38.881779, 1000],
									[-77.026946, 38.882645, 1000],
									[-77.026942, 38.885502, 1000],
									[-77.028054, 38.887449, 1000],
									[-77.02806, 38.892088, 0],
									[-77.03364, 38.892108, 0],
									[-77.033643, 38.899926, 0],
								],
								type: 'LineString',
							},
						},
					],
				},
			},
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
			const apiKey = 'wm3JmgmrSAMz79ffXveo'
			// tile servers and styles
			const styles = [
				{
					title: 'Streets',
					uri: `https://api.maptiler.com/maps/streets/style.json?key=${apiKey}`,
				},
				{
					title: 'Satellite',
					uri: `https://api.maptiler.com/maps/hybrid/style.json?key=${apiKey}`,
				},
				{
					title: 'Outdoor',
					uri: `https://api.maptiler.com/maps/outdoor/style.json?key=${apiKey}`,
				},
				{
					title: 'OpenStreetMap',
					uri: `https://api.maptiler.com/maps/openstreetmap/style.json?key=${apiKey}`,
				},
				{
					title: 'Dark',
					uri: `https://api.maptiler.com/maps/streets-dark/style.json?key=${apiKey}`,
				},
			]
			const restoredStyleObj = styles.find((s) => s.title === this.settings.mapStyle)
			const restoredStyleUri = restoredStyleObj?.uri ?? `https://api.maptiler.com/maps/streets/style.json?key=${apiKey}`
			const mapOptions = {
				container: 'gpxpod-map',
				style: restoredStyleUri,
				center: [0, 0],
				zoom: 1,
				maxPitch: 80,
			}
			// restore map state
			if (this.settings.zoom !== undefined) {
				mapOptions.zoom = this.settings.zoom
			}
			if (this.settings.pitch !== undefined) {
				mapOptions.pitch = this.settings.pitch
			}
			if (this.settings.bearing !== undefined) {
				mapOptions.bearing = this.settings.bearing
			}
			if (this.settings.centerLat !== undefined && this.settings.centerLng !== undefined) {
				mapOptions.center = [parseFloat(this.settings.centerLng), parseFloat(this.settings.centerLat)]
			}
			const map = new Map(mapOptions)
			map.addControl(new NavigationControl({ visualizePitch: true }), 'bottom-right')
			this.scaleControl = new ScaleControl()
			map.addControl(this.scaleControl, 'top-left')

			const options = {
				defaultStyle: this.settings.mapStyle ?? 'Streets',
				eventListeners: {
					onChange: (e, style) => {
						const styleObj = styles.find((s) => s.uri.startsWith(style))
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
			map.addControl(new MapboxStyleSwitcherControl(styles, options))

			this.handleMapEvents(map)

			this.map = map
			map.on('load', () => {
				// tracks are waiting for that to load
				this.mapLoaded = true

				// terrain for maplibre >= 2.2.0
				/*
				map.addSource('terrain', {
					type: 'raster-dem',
					url: 'https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=' + apiKey,
				})
				map.setTerrain({
					source: 'terrain',
					exaggeration: 2.5,
				})
				*/
			})

			subscribe('nav-toggled', this.onNavToggled)
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
