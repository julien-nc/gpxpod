<template>
	<div v-if="active && track.geojson"
		class="charts-container">
		<TrackChart
			:track="track"
			x-axis="time" />
		<TrackChart
			:track="track"
			x-axis="distance" />
	</div>
	<div v-else>
		{{ t('gpxpod', 'No data to display') }}
	</div>
</template>

<script>
import TrackChart from './TrackChart.vue'

export default {
	name: 'TrackChartsSidebarTab',

	components: {
		TrackChart,
	},

	props: {
		track: {
			type: Object,
			required: true,
		},
		active: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.charts-container {
	width: 100%;
	padding: 4px;
}
</style>
