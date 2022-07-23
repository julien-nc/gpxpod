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
import { Map, NavigationControl } from 'maplibre-gl'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import VMarker from './VMarker'

export default {
	name: 'Map',

	components: {
		VMarker,
	},

	props: {
	},

	data() {
		return {
			map: null,
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
			this.map = new Map({
				container: 'gpxpod-map',
				style: `https://api.maptiler.com/maps/streets/style.json?key=${apiKey}`,
				// center: [initialState.lng, initialState.lat],
				// zoom: initialState.zoom
			})
			this.map.addControl(new NavigationControl(), 'bottom-right')

			subscribe('nav-toggled', this.onNavToggled)
		},
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
