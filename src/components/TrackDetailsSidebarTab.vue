<template>
	<div class="details-container">
		<table>
			<tbody>
				<tr v-for="(stat, key) in stats"
					:key="key"
					class="stat-line">
					<td class="label">
						<component :is="stat.icon"
							class="icon"
							:size="20" />
						<span>
							{{ stat.label }}
						</span>
					</td>
					<td>
						{{ stat.value }}
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
import ClockIcon from 'vue-material-design-icons/Clock.vue'
import ArrowLeftRightIcon from 'vue-material-design-icons/ArrowLeftRight.vue'
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

import { formatDuration, metersToElevation, metersToDistance, kmphToSpeed, minPerKmToPace } from '../utils.js'
import moment from '@nextcloud/moment'

export default {
	name: 'TrackDetailsSidebarTab',

	components: {
		ClockIcon,
		ArrowLeftRightIcon,
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
		track: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		stats() {
			return {
				distance: {
					icon: ArrowLeftRightIcon,
					label: t('gpxpod', 'Total distance'),
					value: metersToDistance(this.track.total_distance),
				},
				duration: {
					icon: ClockIcon,
					label: t('gpxpod', 'Total duration'),
					value: formatDuration(this.track.total_duration),
				},
				movingTime: {
					icon: TimerPlayIcon,
					label: t('gpxpod', 'Moving time'),
					value: formatDuration(this.track.moving_time),
				},
				pauseTime: {
					icon: TimerPauseIcon,
					label: t('gpxpod', 'Pause time'),
					value: formatDuration(this.track.stopped_time),
				},
				dateBegin: {
					icon: CalendarWeekBeginIcon,
					label: t('gpxpod', 'Begin'),
					value: this.track.date_begin === null
						? t('gpxpod', 'No date')
						: moment.unix(this.track.date_begin).format('YYYY-MM-DD HH:mm:ss (Z)'),
				},
				dateEnd: {
					icon: CalendarWeekendIcon,
					label: t('gpxpod', 'End'),
					value: this.track.date_end === null
						? t('gpxpod', 'No date')
						: moment.unix(this.track.date_end).format('YYYY-MM-DD HH:mm:ss (Z)'),
				},
				elevationGain: {
					icon: TrendingUpIcon,
					label: t('gpxpod', 'Cumulative elevation gain'),
					value: metersToElevation(this.track.positive_elevation_gain),
				},
				elevationLoss: {
					icon: TrendingDownIcon,
					label: t('gpxpod', 'Cumulative elevation loss'),
					value: metersToElevation(this.track.negative_elevation_gain),
				},
				minElevation: {
					icon: FormatVerticalAlignBottomIcon,
					label: t('gpxpod', 'Minimum elevation'),
					value: metersToElevation(this.track.min_elevation),
				},
				maxElevation: {
					icon: FormatVerticalAlignTopIcon,
					label: t('gpxpod', 'Maximum elevation'),
					value: metersToElevation(this.track.max_elevation),
				},
				maxSpeed: {
					icon: CarSpeedLimiterIcon,
					label: t('gpxpod', 'Maximum speed'),
					value: kmphToSpeed(this.track.max_speed),
				},
				averageSpeed: {
					icon: SpeedometerIcon,
					label: t('gpxpod', 'Average speed'),
					value: kmphToSpeed(this.track.average_speed),
				},
				movingAverageSpeed: {
					icon: SpeedometerMediumIcon,
					label: t('gpxpod', 'Moving average speed'),
					value: kmphToSpeed(this.track.moving_average_speed),
				},
				movingAveragePace: {
					icon: PlaySpeedIcon,
					label: t('gpxpod', 'Moving average pace'),
					value: minPerKmToPace(this.track.moving_pace),
				},
			}
		},
	},

	watch: {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.details-container {
	width: 100%;
	padding: 4px;

	td {
		width: 50%;
		padding: 0 4px;

		&.label {
			display: flex;
			align-items: center;
			.icon {
				margin-right: 4px;
			}
		}
	}
}
</style>
