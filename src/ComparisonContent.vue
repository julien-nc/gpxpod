<template>
	<NcContent app-name="gpxpod">
		<ComparisonNavigation
			:pairs="pairs"
			:stats="stats"
			:selected-pair="selectedPair"
			:selected-criteria="selectedCriteria"
			@pair-selected="onPairSelected"
			@criteria-selected="onCriteriaSelected"
			@show-sidebar-clicked="showSidebar = !showSidebar" />
		<NcAppContent
			:list-max-width="50"
			:list-min-width="20"
			:list-size="20"
			:show-details="false">
			<MaplibreMap ref="map"
				:comparison-geojsons="selectedPairGeojsons"
				:comparison-criteria="selectedCriteria"
				:settings="settings"
				:show-mouse-position-control="settings.show_mouse_position_control === '1'"
				:unit="distanceUnit"
				:with-top-left-button="true"
				@save-options="saveOptions"
				@map-state-change="saveOptions" />
		</NcAppContent>
		<ComparisonSidebar
			:show="showSidebar"
			:settings="settings"
			:stats="stats"
			@close="showSidebar = false" />
	</NcContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'

import ComparisonNavigation from './components/comparison/ComparisonNavigation.vue'
import ComparisonSidebar from './components/comparison/ComparisonSidebar.vue'
import MaplibreMap from './components/map/MaplibreMap.vue'

import { loadState } from '@nextcloud/initial-state'
import { emit } from '@nextcloud/event-bus'
import { basename } from '@nextcloud/paths'

export default {
	name: 'ComparisonContent',

	components: {
		ComparisonSidebar,
		MaplibreMap,
		ComparisonNavigation,
		NcAppContent,
		NcContent,
	},

	props: {
	},

	data() {
		return {
			settings: loadState('gpxpod', 'settings'),
			names: loadState('gpxpod', 'names'),
			geojsons: loadState('gpxpod', 'geojsons'),
			stats: loadState('gpxpod', 'stats'),
			selectedPair: null,
			selectedCriteria: 'time',
			showSidebar: false,
		}
	},

	computed: {
		distanceUnit() {
			return this.settings.distance_unit ?? 'metric'
		},
		pairs() {
			const result = []
			for (let i = 0; i < this.names.length; i++) {
				for (let j = i + 1; j < this.names.length; j++) {
					result.push({
						id: this.names[i] + '|' + this.names[j],
						value: [this.names[i], this.names[j]],
						label: basename(this.names[i]) + ' -> ' + basename(this.names[j]),
					})
				}
			}
			console.debug('pairs', result)
			return result
		},
		selectedPairGeojsons() {
			if (this.selectedPair === null) {
				return null
			}
			const tPath1 = this.selectedPair.value[0]
			const tPath2 = this.selectedPair.value[1]
			const g1 = this.geojsons[tPath1][tPath2]
			const g2 = this.geojsons[tPath2][tPath1]
			return [g1, g2]
		},
	},

	watch: {
	},

	beforeMount() {
		console.debug('gpxComp settings', this.settings)
		console.debug('gpxComp pairs', this.pairs)
		console.debug('gpxComp geojsons', this.geojsons)
		console.debug('gpxComp stats', this.stats)

		if (this.pairs.length > 0) {
			this.selectedPair = this.pairs[0]
			const bounds = this.getSelectedPairBounds()
			this.settings.initialBounds = bounds
			console.debug('[gpxpod] comparison initial bounds', bounds)
		}
	},

	mounted() {
		emit('nav-toggled')
	},

	methods: {
		onPairSelected(newValue) {
			this.selectedPair = newValue
			this.zoomOnComparisonBounds()
		},
		onCriteriaSelected(newValue) {
			this.selectedCriteria = newValue
		},
		getSelectedPairBounds() {
			const gs = [this.selectedPairGeojsons[0], this.selectedPairGeojsons[1]]
			const featureNorths = []
			const featureSouths = []
			const featureEasts = []
			const featureWests = []
			gs.forEach(g => {
				g.features.forEach(f => {
					const lats = f.geometry.coordinates.map(c => c[1])
					featureNorths.push(lats.reduce((acc, val) => Math.max(acc, val)))
					featureSouths.push(lats.reduce((acc, val) => Math.min(acc, val)))
					const lons = f.geometry.coordinates.map(c => c[0])
					featureEasts.push(lons.reduce((acc, val) => Math.max(acc, val)))
					featureWests.push(lons.reduce((acc, val) => Math.min(acc, val)))
				})
			})
			return {
				north: featureNorths.reduce((acc, val) => Math.max(acc, val)),
				south: featureSouths.reduce((acc, val) => Math.min(acc, val)),
				east: featureEasts.reduce((acc, val) => Math.max(acc, val)),
				west: featureWests.reduce((acc, val) => Math.min(acc, val)),
			}
		},
		zoomOnComparisonBounds() {
			const bounds = this.getSelectedPairBounds()
			console.debug('[gpxpod] comparison zoom on', bounds)
			emit('zoom-on-bounds', bounds)
		},
		saveOptions(values) {
			Object.assign(this.settings, values)
		},
	},
}
</script>

<style scoped lang="scss">
body {
	min-height: 100%;
	height: auto;
}
</style>
