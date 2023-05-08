<template>
	<NcListItem
		class="trackItem"
		:title="track.name"
		:active="selected"
		:bold="selected"
		:details="details"
		:counter-number="deleteCounter"
		:force-display-actions="true"
		@click="onItemClick">
		<template #subtitle>
			{{ subtitle }}
		</template>
		<div v-if="track.isEnabled || track.loading"
			slot="icon"
			class="trackItemDot">
			<NcLoadingIcon v-if="track.loading" />
			<NcColorPicker v-else
				class="app-navigation-entry-bullet-wrapper trackColorPicker"
				:value="track.color"
				@input="updateColor">
				<ColoredDot
					ref="colorDot"
					class="color-dot"
					:color="dotColor"
					:size="24" />
			</NcColorPicker>
		</div>
		<template #actions>
			ACTIONS
		</template>
		<template #extra>
			<div v-if="false" class="icon-selector">
				<CheckboxMarkedIcon v-if="selected" class="selected" :size="20" />
				<CheckboxBlankOutlineIcon v-else :size="20" />
			</div>
		</template>
	</NcListItem>
</template>

<script>
import CheckboxMarkedIcon from 'vue-material-design-icons/CheckboxMarked.vue'
import CheckboxBlankOutlineIcon from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'

import ColoredDot from './ColoredDot.vue'

import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcColorPicker from '@nextcloud/vue/dist/Components/NcColorPicker.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import moment from '@nextcloud/moment'
import { emit } from '@nextcloud/event-bus'

import { delay, Timer, metersToDistance } from '../utils.js'
import { COLOR_CRITERIAS } from '../constants.js'

export default {
	name: 'TrackListItem',

	components: {
		ColoredDot,
		NcListItem,
		CheckboxBlankOutlineIcon,
		CheckboxMarkedIcon,
		NcColorPicker,
		NcLoadingIcon,
	},

	props: {
		track: {
			type: Object,
			required: true,
		},
		index: {
			type: Number,
			required: true,
		},
		count: {
			type: Number,
			required: true,
		},
		selected: {
			type: Boolean,
			default: false,
		},
		settings: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			deleteCounter: 0,
			timer: null,
		}
	},

	computed: {
		timerOn() {
			return this.deleteCounter > 0
		},
		dotColor() {
			return this.track.colorCriteria === COLOR_CRITERIAS.none.id && this.track.colorExtensionCriteria === ''
				? this.track.color || '#0693e3'
				: 'gradient'
		},
		formattedTrackDate() {
			return moment.unix(this.track.date_begin).format('L')
		},
		indexText() {
			return '[' + this.index + '/' + this.count + ']'
		},
		subtitle() {
			return t('gpxpod', 'Total distance') + ': ' + metersToDistance(this.track.total_distance, this.settings.distance_unit)
		},
		deleteIconComponent() {
			return this.timerOn
				? UndoIcon
				: DeleteIcon
		},
		deleteIconTitle() {
			return this.timerOn
				? t('gpxpod', 'Cancel')
				: t('gpxpod', 'Delete this track')
		},
		details() {
			return this.selected
				? this.indexText + ' ' + this.formattedTrackDate
				: this.formattedTrackDate
		},
	},

	mounted() {
	},

	methods: {
		onItemClick() {
			emit('track-clicked', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onDeleteClick(e) {
			// stop timer
			if (this.timerOn) {
				this.deleteCounter = 0
				if (this.timer) {
					this.timer.pause()
					delete this.timer
				}
			} else {
				// start timer
				this.deleteCounter = 7
				this.timerLoop()
			}
		},
		timerLoop() {
			// on each loop, check if finished or not
			if (this.timerOn) {
				this.timer = new Timer(() => {
					this.deleteCounter--
					this.timerLoop()
				}, 1000)
			} else {
				emit('delete-track', this.track)
			}
		},
		updateColor(color) {
			delay(() => {
				this.applyUpdateColor(color)
			}, 1000)()
		},
		applyUpdateColor(color) {
			emit('track-color-changed', { trackId: this.track.id, dirId: this.track.directoryId, color })
		},
	},
}
</script>

<style scoped lang="scss">
.trackItem {
	list-style: none;
}

.icon-selector {
	display: flex;
	justify-content: right;
	padding-right: 8px;
	position: absolute;
	right: 14px;
	bottom: 12px;
}
</style>
