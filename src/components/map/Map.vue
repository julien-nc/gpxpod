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
		</div>
	</div>
</template>

<script>
import { Map, NavigationControl, ScaleControl } from 'maplibre-gl'
import { MapboxStyleSwitcherControl } from 'mapbox-gl-style-switcher'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import VMarker from './VMarker'

import 'mapbox-gl-style-switcher/styles.css'

export default {
	name: 'Map',

	components: {
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
			scaleControl: null,
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
			const mapOptions = {
				container: 'gpxpod-map',
				style: `https://api.maptiler.com/maps/streets/style.json?key=${apiKey}`,
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
			this.map = new Map(mapOptions)
			this.map.addControl(new NavigationControl({ visualizePitch: true }), 'bottom-right')
			this.scaleControl = new ScaleControl()
			this.map.addControl(this.scaleControl, 'top-left')

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
			const options = {
				defaultStyle: 'Streets',
				eventListeners: {
					// return true if you want to stop execution
					//           onOpen: (event: MouseEvent) => boolean;
					//           onSelect: (event: MouseEvent) => boolean;
					//           onChange: (event: MouseEvent, style: string) => boolean;
				},
			}
			this.map.addControl(new MapboxStyleSwitcherControl(styles, options))

			this.handleMapEvents()

			subscribe('nav-toggled', this.onNavToggled)
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
