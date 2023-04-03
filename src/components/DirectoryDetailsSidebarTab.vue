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
import { formatDuration, metersToElevation, metersToDistance, kmphToSpeed, minPerKmToPace } from '../utils.js'
import moment from '@nextcloud/moment'

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

export default {
	name: 'DirectoryDetailsSidebarTab',

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
		directory: {
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
		}
	},

	computed: {
		tsBegin() {
			const tracksArray = Object.values(this.directory.tracks)
			let minTs = tracksArray[0].date_begin
			for (let i = 1; i < tracksArray.length; i++) {
				const ts = tracksArray[i].date_begin
				if (ts < minTs) {
					minTs = ts
				}
			}
			return minTs
		},
		tsEnd() {
			const tracksArray = Object.values(this.directory.tracks)
			let maxTs = moment(tracksArray[0].date_end).unix()
			for (let i = 1; i < tracksArray.length; i++) {
				const ts = moment(tracksArray[i].date_end).unix()
				if (ts > maxTs) {
					maxTs = ts
				}
			}
			return maxTs
		},
		stats() {
			if (Object.values(this.directory.tracks).length === 0) {
				return {}
			}
			return {
				distance: {
					icon: ArrowLeftRightIcon,
					label: t('gpxpod', 'Cumulative total distance'),
					value: metersToDistance(this.sumAttribute('total_distance'), this.settings.distance_unit),
				},
				duration: {
					icon: ClockIcon,
					label: t('gpxpod', 'Cumulative total duration'),
					value: formatDuration(this.sumAttribute('total_duration')),
				},
				movingTime: {
					icon: TimerPlayIcon,
					label: t('gpxpod', 'Moving time'),
					value: formatDuration(this.sumAttribute('moving_time')),
				},
				pauseTime: {
					icon: TimerPauseIcon,
					label: t('gpxpod', 'Pause time'),
					value: formatDuration(this.sumAttribute('stopped_time')),
				},
				dateBegin: {
					icon: CalendarWeekBeginIcon,
					label: t('gpxpod', 'Begin'),
					value: moment.unix(this.tsBegin).format('YYYY-MM-DD HH:mm:ss (Z)'),
				},
				dateEnd: {
					icon: CalendarWeekendIcon,
					label: t('gpxpod', 'End'),
					value: moment.unix(this.tsEnd).format('YYYY-MM-DD HH:mm:ss (Z)'),
				},
				elevationGain: {
					icon: TrendingUpIcon,
					label: t('gpxpod', 'Cumulative elevation gain'),
					value: metersToElevation(this.sumAttribute('positive_elevation_gain'), this.settings.distance_unit),
				},
				elevationLoss: {
					icon: TrendingDownIcon,
					label: t('gpxpod', 'Cumulative elevation loss'),
					value: metersToElevation(this.sumAttribute('negative_elevation_gain'), this.settings.distance_unit),
				},
				minElevation: {
					icon: FormatVerticalAlignBottomIcon,
					label: t('gpxpod', 'Minimum elevation'),
					value: metersToElevation(
						Math.min.apply(
							null,
							Object.values(this.directory.tracks).map(t => t.min_elevation)
						),
						this.settings.distance_unit
					),
				},
				maxElevation: {
					icon: FormatVerticalAlignTopIcon,
					label: t('gpxpod', 'Maximum elevation'),
					value: metersToElevation(
						Math.max.apply(
							null,
							Object.values(this.directory.tracks).map(t => t.max_elevation)
						),
						this.settings.distance_unit
					),
				},
				maxSpeed: {
					icon: CarSpeedLimiterIcon,
					label: t('gpxpod', 'Maximum speed'),
					value: kmphToSpeed(
						Math.max.apply(
							null,
							Object.values(this.directory.tracks).map(t => t.max_speed)
						),
						this.settings.distance_unit
					),
				},
				averageSpeed: {
					icon: SpeedometerIcon,
					label: t('gpxpod', 'Average speed'),
					value: kmphToSpeed(
						this.sumAttribute('average_speed') / Object.keys(this.directory.tracks).length,
						this.settings.distance_unit
					),
				},
				movingAverageSpeed: {
					icon: SpeedometerMediumIcon,
					label: t('gpxpod', 'Moving average speed'),
					value: kmphToSpeed(
						this.sumAttribute('moving_average_speed') / Object.keys(this.directory.tracks).length,
						this.settings.distance_unit
					),
				},
				movingAveragePace: {
					icon: PlaySpeedIcon,
					label: t('gpxpod', 'Moving average pace'),
					value: minPerKmToPace(
						this.sumAttribute('moving_pace') / Object.keys(this.directory.tracks).length,
						this.settings.distance_unit
					),
				},
			}
		},
	},

	watch: {
	},

	methods: {
		sumAttribute(attr) {
			let sum = 0
			Object.values(this.directory.tracks).forEach(track => {
				sum += track[attr]
			})
			return sum
		},
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
