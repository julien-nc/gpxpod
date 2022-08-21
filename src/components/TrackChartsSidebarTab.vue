<template>
	<div v-if="active && track.geojson"
		class="charts-container">
		<TrackChartByTime
			:track="track" />
	</div>
	<div v-else>
		{{ t('gpxpod', 'No data to display') }}
	</div>
</template>

<script>
import TrackChartByTime from './TrackChartByTime.vue'

export default {
	name: 'TrackChartsSidebarTab',

	components: {
		TrackChartByTime,
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
