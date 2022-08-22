<template>
	<div v-if="active && track.geojson"
		class="charts-container">
		<TrackChart
			:track="track"
			:x-axis="settings.chart_x_axis" />
		<hr>
		<div class="field">
			<label for="prefChartType">
				<AxisXArrowIcon
					class="icon"
					:size="20" />
				{{ t('gpxpod', 'Chart X axis') }}
			</label>
			<select
				id="prefXAxis"
				:value="settings.chart_x_axis"
				@change="onXAxisChange">
				<option value="time">
					{{ t('gpxpod', 'Elapsed time') }}
				</option>
				<option value="date">
					{{ t('gpxpod', 'Date') }}
				</option>
				<option value="distance">
					{{ t('gpxpod', 'Traveled distance') }}
				</option>
			</select>
		</div>
	</div>
	<div v-else>
		{{ t('gpxpod', 'No data to display') }}
	</div>
</template>

<script>
import AxisXArrowIcon from 'vue-material-design-icons/AxisXArrow.vue'
import TrackChart from './TrackChart.vue'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'TrackChartsSidebarTab',

	components: {
		TrackChart,
		AxisXArrowIcon,
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
		settings: {
			type: Object,
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
		onXAxisChange(e) {
			emit('save-settings', { chart_x_axis: e.target.value })
		},
	},
}
</script>

<style scoped lang="scss">
.charts-container {
	width: 100%;
	padding: 4px;

	.field {
		display: flex;
		align-items: center;
		// justify-content: center;

		label {
			display: flex;
			align-items: center;
			margin-right: 4px;

			> *:first-child {
				margin-right: 4px;
			}
		}
	}
}
</style>
