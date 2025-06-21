<template>
	<NcListItem
		:class="{ trackItem: true }"
		:name="track.name"
		:title="track.name"
		:active="track.isEnabled"
		:bold="track.isEnabled"
		:counter-number="deleteCounter"
		:force-display-actions="true"
		@update:menuOpen="onUpdateMenuOpen"
		@mouseenter.native="onHoverIn"
		@mouseleave.native="onHoverOut"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@click="onItemClick">
		<template #subname>
			{{ subtitle }}
		</template>
		<template #subtitle>
			{{ subtitle }}
		</template>
		<template v-if="track.isEnabled || track.loading" #icon>
			<NcLoadingIcon v-if="track.loading" />
			<NcColorPicker v-else
				class="app-navigation-entry-bullet-wrapper"
				:model-value="track.color"
				@update:model-value="updateColor">
				<template #default="{ attrs }">
					<ColoredDot
						v-bind="attrs"
						ref="colorDot"
						class="color-dot"
						:color="dotColor"
						:size="24" />
				</template>
			</NcColorPicker>
		</template>
		<template #actions>
			<template v-if="timerOn">
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="onDeleteTrackClick">
					<template #icon>
						<UndoIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Cancel deletion') }}
				</NcActionButton>
			</template>
			<template v-else-if="!criteriaActionsOpen">
				<NcActionButton
					:close-after-click="true"
					@click="onDetailsClick">
					<template #icon>
						<InformationOutlineIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Details') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="onShareClick">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Share') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onZoomClick">
					<template #icon>
						<MagnifyExpandIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Zoom to bounds') }}
				</NcActionButton>
				<NcActionLink
					:close-after-click="true"
					:href="downloadLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Download') }}
				</NcActionLink>
				<NcActionButton
					:close-after-click="true"
					@click="onMenuColorClick">
					<template #icon>
						<PaletteIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Change color') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="false"
					:is-menu="true"
					@click="criteriaActionsOpen = true">
					<template #icon>
						<BrushIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Change color criteria') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="onCorrectElevationClick">
					<template #icon>
						<ChartAreasplineVariantIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Correct elevations') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="onDeleteTrackClick">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Delete this file') }}
				</NcActionButton>
			</template>
			<template v-else>
				<NcActionButton :close-after-click="false"
					@click="criteriaActionsOpen = false">
					<template #icon>
						<ChevronLeftIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Back') }}
				</NcActionButton>
				<NcActionRadio v-for="(c, ckey) in COLOR_CRITERIAS"
					:key="ckey"
					name="criteria"
					:model-value="track.colorExtensionCriteria === '' && track.colorCriteria === c.id"
					@change="onCriteriaChange(c.id)">
					{{ c.label }}
				</NcActionRadio>
				<NcActionRadio v-for="ext in track.extensions?.trackpoint"
					:key="'extension-trackpoint-' + ext"
					name="criteria"
					:model-value="track.colorExtensionCriteriaType === 'trackpoint' && track.colorExtensionCriteria === ext"
					@change="onColorExtensionCriteriaChange(ext, 'trackpoint')">
					{{ getExtensionLabel(ext) }}
				</NcActionRadio>
				<NcActionRadio v-for="ext in track.extensions?.unsupported"
					:key="'extension-unsupported-' + ext"
					name="criteria"
					:model-value="track.colorExtensionCriteriaType === 'unsupported' && track.colorExtensionCriteria === ext"
					@change="onColorExtensionCriteriaChange(ext, 'unsupported')">
					{{ getExtensionLabel(ext) }}
				</NcActionRadio>
			</template>
		</template>
		<template #extra>
			<div v-if="false" class="icon-selector">
				<CheckboxMarkedIcon v-if="false" class="selected" :size="20" />
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
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import MagnifyExpandIcon from 'vue-material-design-icons/MagnifyExpand.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import BrushIcon from 'vue-material-design-icons/Brush.vue'
import ChartAreasplineVariantIcon from 'vue-material-design-icons/ChartAreasplineVariant.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'

import ColoredDot from './ColoredDot.vue'

import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'

import moment from '@nextcloud/moment'
import { emit } from '@nextcloud/event-bus'

import { Timer, metersToDistance, formatDuration } from '../utils.js'
import { COLOR_CRITERIAS } from '../constants.js'
import TrackItem from '../mixins/TrackItem.js'

export default {
	name: 'TrackListItem',

	components: {
		ColoredDot,
		NcListItem,
		CheckboxBlankOutlineIcon,
		CheckboxMarkedIcon,
		NcColorPicker,
		NcLoadingIcon,
		NcActionButton,
		NcActionRadio,
		InformationOutlineIcon,
		ShareVariantIcon,
		MagnifyExpandIcon,
		PaletteIcon,
		BrushIcon,
		ChartAreasplineVariantIcon,
		DeleteIcon,
		UndoIcon,
		ChevronLeftIcon,
	},

	mixins: [
		TrackItem,
	],

	inject: ['isPublicPage'],

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
			menuOpen: false,
			criteriaActionsOpen: false,
			COLOR_CRITERIAS,

			deleteCounter: 0,
			timer: null,
		}
	},

	computed: {
		timerOn() {
			return this.deleteCounter > 0
		},
		indexText() {
			return '[' + this.index + '/' + this.count + ']'
		},
		subtitle() {
			const items = [
				this.trackDate,
				metersToDistance(this.track.total_distance, this.settings.distance_unit),
				this.trackDuration,
			]
			if (this.track.isEnabled) {
				return this.indexText + ' ' + items.join(', ')
			}
			return items.join(', ')
		},
		trackDuration() {
			return this.track.total_duration && this.track.total_duration > 0
				? formatDuration(this.track.total_duration)
				: t('gpxpod', 'No duration')
		},
		trackDate() {
			return this.track.date_begin
				? moment.unix(this.track.date_begin).format('L')
				: t('gpxpod', 'No date')
		},
	},

	mounted() {
	},

	methods: {
		onItemClick(e) {
			if (!e.target.classList.contains('color-dot')) {
				emit('track-clicked', { trackId: this.track.id, dirId: this.track.directoryId })
			}
		},
		onDeleteTrackClick(e) {
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
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.criteriaActionsOpen = false
			}
			this.menuOpen = isOpen
		},
	},
}
</script>

<style scoped lang="scss">
.trackItem {
	list-style: none;
	.icon-selector {
		display: flex;
		justify-content: right;
		padding-right: 8px;
		position: absolute;
		right: 14px;
		bottom: 12px;
	}
}
</style>
