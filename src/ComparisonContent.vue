<template>
	<NcContent app-name="gpxpod">
		<ComparisonNavigation
			:pairs="pairs"
			:stats="stats"
			:selected-pair="selectedPair"
			@pair-selected="onPairSelected" />
		<NcAppContent
			:list-max-width="50"
			:list-min-width="20"
			:list-size="20"
			:show-details="false">
			<MaplibreMap ref="map"
				:settings="settings"
				:show-mouse-position-control="settings.show_mouse_position_control === '1'"
				:comparison-geojson="{}"
				:unit="distanceUnit" />
		</NcAppContent>
	</NcContent>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { basename } from '@nextcloud/paths'

const NcAppContent = () => import('@nextcloud/vue/dist/Components/NcAppContent.js')
const NcContent = () => import('@nextcloud/vue/dist/Components/NcContent.js')

const ComparisonNavigation = () => import('./components/comparison/ComparisonNavigation.vue')
const MaplibreMap = () => import('./components/map/MaplibreMap.vue')

export default {
	name: 'ComparisonContent',

	components: {
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
			pairs: loadState('gpxpod', 'pairs'),
			stats: loadState('gpxpod', 'stats'),
			selectedPair: null,
		}
	},

	computed: {
		distanceUnit() {
			return this.settings.distance_unit ?? 'metric'
		},
	},

	watch: {
	},

	beforeMount() {
		console.debug('gpxcomp settings', this.settings)
		console.debug('gpxcomp pairs', this.pairs)
		console.debug('gpxcomp stats', this.stats)

		if (this.pairs.length > 0) {
			const p = this.pairs[0]
			this.selectedPair = {
				id: p.track1 + '|' + p.track2,
				value: [p.track1, p.track2],
				label: basename(p.track1) + ' -> ' + basename(p.track2),
			}
		}
	},

	mounted() {
		emit('nav-toggled')
	},

	beforeDestroy() {
	},

	methods: {
		onPairSelected(newValue) {
			console.debug('selected', newValue)
			this.selectedPair = newValue
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
