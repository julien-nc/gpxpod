<template>
	<div v-if="active && track.geojson"
		class="charts-container">
		<NcNoteCard type="info">
			{{ t('gpxpod', 'You can zoom on the chart by holding the SHIFT key and using the mouse wheel. You can then pan the chart by dragging it with the mouse.') }}
		</NcNoteCard>
		<TrackChart
			:track="track"
			:x-axis="settings.chart_x_axis ?? 'time'"
			:extension="selectedExtension?.value ?? ''"
			:extension-type="selectedExtension?.type ?? ''"
			:chart-y-scale="chartYScale"
			:settings="settings" />
		<NcButton @click="onResetZoom">
			{{ t('gpxpod', 'Reset zoom') }}
		</NcButton>
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
				:value="settings.chart_x_axis ?? 'time'"
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
		<div class="field">
			<label for="chartYScale">
				<RulerIcon
					class="icon"
					:size="20" />
				{{ t('gpxpod', 'Show Y axis scale for') }}
			</label>
			<select
				id="chartYScale"
				v-model="chartYScale">
				<option value="none">
					{{ t('gpxpod', 'None') }}
				</option>
				<option value="elevation">
					{{ t('gpxpod', 'Elevation') }}
				</option>
				<option value="speed">
					{{ t('gpxpod', 'Speed') }}
				</option>
				<option value="pace">
					{{ t('gpxpod', 'Pace') }}
				</option>
				<option value="extension">
					{{ t('gpxpod', 'Extension') }}
				</option>
			</select>
		</div>
		<div v-if="track.extensions?.unsupported?.length > 0 || track.extensions?.trackpoint?.length > 0"
			class="field">
			<label for="data-extension-select">
				<DatabaseMarkerOutlineIcon
					class="icon"
					:size="20" />
				{{ t('gpxpod', 'Track extension property to draw') }}
			</label>
			<!--select
				id="data-extension-select"
				v-model="extension">
				<option value="">
					{{ t('gpxpod', 'None') }}
				</option>
				<option v-for="ext in track.extensions"
					:key="ext"
					:value="ext">
					{{ getExtensionLabel(ext) }}
				</option>
			</select-->
			<NcSelect
				v-model="selectedExtension"
				:options="formattedExtensions"
				input-id="extension-select" />
		</div>
		<NcCheckboxRadioSwitch
			:model-value="settings.follow_chart_hover === '1'"
			@update:model-value="onCheckboxChanged($event, 'follow_chart_hover')">
			{{ t('gpxpod', 'Center map on chart hovered point') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:model-value="settings.chart_hover_show_detailed_popup === '1'"
			@update:model-value="onCheckboxChanged($event, 'chart_hover_show_detailed_popup')">
			{{ t('gpxpod', 'Show details of hovered point on the map') }}
		</NcCheckboxRadioSwitch>
	</div>
	<div v-else>
		<NcEmptyContent
			:name="t('gpxpod', 'No data to display')"
			:title="t('gpxpod', 'No data to display')">
			<template #icon>
				<DatabaseOffOutlineIcon />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import DatabaseMarkerOutlineIcon from 'vue-material-design-icons/DatabaseMarkerOutline.vue'
import AxisXArrowIcon from 'vue-material-design-icons/AxisXArrow.vue'
import DatabaseOffOutlineIcon from 'vue-material-design-icons/DatabaseOffOutline.vue'
import RulerIcon from 'vue-material-design-icons/Ruler.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcButton from '@nextcloud/vue/components/NcButton'

import TrackChart from './TrackChart.vue'

import { formatExtensionKey } from '../utils.js'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'TrackChartsSidebarTab',

	components: {
		TrackChart,
		AxisXArrowIcon,
		DatabaseOffOutlineIcon,
		DatabaseMarkerOutlineIcon,
		RulerIcon,
		NcEmptyContent,
		NcCheckboxRadioSwitch,
		NcSelect,
		NcNoteCard,
		NcButton,
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
			selectedExtension: null,
			chartYScale: 'none',
		}
	},

	computed: {
		formattedExtensions() {
			const result = []
			if (this.track.extensions?.trackpoint?.length) {
				result.push(...this.track.extensions.trackpoint.map(ext => {
					return {
						id: 'trkpt-' + ext,
						value: ext,
						type: 'trackpoint',
						label: this.getExtensionLabel(ext),
					}
				}))
			}
			if (this.track.extensions?.unsupported?.length) {
				result.push(...this.track.extensions.unsupported.map(ext => {
					return {
						id: 'unsup-' + ext,
						value: ext,
						type: 'unsupported',
						label: this.getExtensionLabel(ext),
					}
				}))
			}
			return result
		},
	},

	watch: {
		track(val) {
			this.selectedExtension = null
		},
	},

	methods: {
		onXAxisChange(e) {
			emit('save-settings', { chart_x_axis: e.target.value })
		},
		onCheckboxChanged(newValue, key) {
			emit('save-settings', { [key]: newValue ? '1' : '0' })
		},
		getExtensionLabel(ext) {
			return formatExtensionKey(ext)
		},
		onResetZoom() {
			emit('chart-zoom-reset')
		},
	},
}
</script>

<style scoped lang="scss">
.charts-container {
	width: 100%;
	padding: 4px;
	display: flex;
	flex-direction: column;
	align-items: start;

	.field {
		width: 100%;
		display: flex;
		flex-direction: column;
		justify-content: center;

		label {
			margin-top: 8px;
			display: flex;
			align-items: center;

			> *:first-child {
				margin-right: 4px;
			}
		}
	}
}
</style>
