<template>
	<NcAppSidebar v-show="show"
		:title="t('gpxpod', 'Track comparison')"
		:compact="true"
		active="global-table"
		@close="$emit('close')">
		<!--template #description /-->
		<NcAppSidebarTab
			id="global-table"
			:name="t('gpxpod', 'Stats')"
			:order="1">
			<template #icon>
				<TableLargeIcon :size="20" />
			</template>
			<table>
				<thead>
					<th>
						{{ t('gpxpod', 'Value') }}
					</th>
					<th v-for="path in trackPaths"
						:key="path">
						{{ basename(path) }}
					</th>
				</thead>
				<tbody>
					<tr v-for="(v, fieldName) in fields"
						:key="fieldName"
						class="stat-line">
						<td>
							<div class="label">
								<component :is="v.icon"
									class="icon"
									:size="20" />
								<span>
									{{ v.label }}
								</span>
							</div>
						</td>
						<td v-for="path in trackPaths"
							:key="path + '.' + fieldName"
							class="value">
							{{ v.format(stats[path][fieldName]) }}
						</td>
					</tr>
				</tbody>
			</table>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import TableLargeIcon from 'vue-material-design-icons/TableLarge.vue'
import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import ClockIcon from 'vue-material-design-icons/Clock.vue'
import ArrowLeftRightIcon from 'vue-material-design-icons/ArrowLeftRight.vue'
import TimerIcon from 'vue-material-design-icons/Timer.vue'
import TimerPauseIcon from 'vue-material-design-icons/TimerPause.vue'
import TimerPlayIcon from 'vue-material-design-icons/TimerPlay.vue'
import CalendarWeekBeginIcon from 'vue-material-design-icons/CalendarWeekBegin.vue'
import CalendarWeekendIcon from 'vue-material-design-icons/CalendarWeekend.vue'
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import TrendingDownIcon from 'vue-material-design-icons/TrendingDown.vue'
import FormatVerticalAlignTopIcon from 'vue-material-design-icons/FormatVerticalAlignTop.vue'
import FormatVerticalAlignBottomIcon from 'vue-material-design-icons/FormatVerticalAlignBottom.vue'
import CarSpeedLimiterIcon from 'vue-material-design-icons/CarSpeedLimiter.vue'
import SpeedometerIcon from 'vue-material-design-icons/Speedometer.vue'
import SpeedometerMediumIcon from 'vue-material-design-icons/SpeedometerMedium.vue'
import PlaySpeedIcon from 'vue-material-design-icons/PlaySpeed.vue'

import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'

import { basename } from '@nextcloud/paths'
import moment from '@nextcloud/moment'
import { formatDuration, kmphToSpeed, metersToDistance, metersToElevation } from '../../utils.js'

export default {
	name: 'ComparisonSidebar',

	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		TableLargeIcon,
		DotsHorizontalIcon,
		ClockIcon,
		ArrowLeftRightIcon,
		TimerIcon,
		TimerPauseIcon,
		TimerPlayIcon,
		CalendarWeekBeginIcon,
		CalendarWeekendIcon,
		TrendingUpIcon,
		TrendingDownIcon,
		FormatVerticalAlignTopIcon,
		FormatVerticalAlignBottomIcon,
		CarSpeedLimiterIcon,
		SpeedometerIcon,
		SpeedometerMediumIcon,
		PlaySpeedIcon,
	},

	props: {
		show: {
			type: Boolean,
			required: true,
		},
		stats: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			trackPaths: Object.keys(this.stats),
			fields: {
				length_2d: {
					label: t('gpxpod', 'Total distance'),
					icon: ArrowLeftRightIcon,
					format: (value) => metersToDistance(value, this.settings.distance_unit),
				},
				/*
				length_3d: {
					label: t('gpxpod', 'Distance 3D'),
					icon: ArrowLeftRightIcon,
					format: (value) => metersToDistance(value, this.settings.distance_unit),
				},
				*/
				total_duration: {
					label: t('gpxpod', 'Total duration'),
					icon: TimerIcon,
					format: formatDuration,
				},
				moving_time: {
					label: t('gpxpod', 'Moving time'),
					icon: TimerPlayIcon,
					format: formatDuration,
				},
				stopped_time: {
					label: t('gpxpod', 'Pause time'),
					icon: TimerPauseIcon,
					format: formatDuration,
				},
				moving_avg_speed: {
					label: t('gpxpod', 'Moving average speed'),
					icon: SpeedometerMediumIcon,
					format: (value) => kmphToSpeed(value, this.settings.distance_unit),
				},
				avg_speed: {
					label: t('gpxpod', 'Average speed'),
					icon: SpeedometerIcon,
					format: (value) => kmphToSpeed(value, this.settings.distance_unit),
				},
				max_speed: {
					label: t('gpxpod', 'Maximum speed'),
					icon: CarSpeedLimiterIcon,
					format: (value) => kmphToSpeed(value, this.settings.distance_unit),
				},
				total_uphill: {
					label: t('gpxpod', 'Cumulative elevation gain'),
					icon: TrendingUpIcon,
					format: (value) => metersToElevation(value, this.settings.distance_unit),
				},
				total_downhill: {
					label: t('gpxpod', 'Cumulative elevation loss'),
					icon: TrendingDownIcon,
					format: (value) => metersToElevation(value, this.settings.distance_unit),
				},
				started: {
					label: t('gpxpod', 'Begin'),
					icon: CalendarWeekBeginIcon,
					format: this.formatDate,
				},
				ended: {
					label: t('gpxpod', 'End'),
					icon: CalendarWeekendIcon,
					format: this.formatDate,
				},
				nbpoints: {
					label: t('gpxpod', 'Number of points'),
					icon: DotsHorizontalIcon,
					format: (value) => value,
				},
			},
		}
	},

	computed: {
	},

	methods: {
		basename(n) {
			return basename(n)
		},
		formatDate(value) {
			return value === null
				? t('gpxpod', 'No date')
				: moment.unix(value).format('YYYY-MM-DD HH:mm:ss (Z)')
		},
	},
}
</script>

<style lang="scss" scoped>
table, th, td {
	border: 1px solid var(--color-border);
}

table {
	display: block;
	overflow-x: scroll;
	scrollbar-width: auto;
	scrollbar-color: var(--color-primary);
	border-collapse: collapse;

	thead {
		padding-bottom: 4px;

		th {
			font-weight: bold;
			padding-left: 4px;
			padding-right: 4px;
		}
	}

	td {
		padding: 0 4px;

		.label {
			display: flex;
			align-items: center;

			.icon {
				margin-right: 4px;
			}
		}

		&.value {
			white-space: normal;
		}
	}
}
</style>
