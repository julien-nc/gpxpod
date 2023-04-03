<template>
	<NcAppNavigationItem
		:name="directoryName"
		:title="directory.path"
		:class="{ openDirectory: directory.isOpen }"
		:loading="directory.loading"
		:allow-collapse="true"
		:open="directory.isOpen"
		:force-menu="true"
		:force-display-actions="true"
		:menu-open="menuOpen"
		@click="onDirectoryClick"
		@update:open="onDirectoryOpen"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@update:menuOpen="onUpdateMenuOpen"
		@mouseenter.native="$emit('hover-in')"
		@mouseleave.native="$emit('hover-out')">
		<template #icon>
			<FolderIcon v-if="directory.isOpen"
				:size="20" />
			<FolderOutlineIcon v-else
				:size="20" />
		</template>
		<template #counter>
			{{ Object.keys(directory.tracks).length || '' }}
		</template>
		<template #actions>
			<template v-if="sortActionsOpen && !isPublicPage">
				<NcActionButton :close-after-click="false"
					@click="sortActionsOpen = false">
					<template #icon>
						<ChevronLeft :size="20" />
					</template>
					{{ t('gpxpod', 'Back') }}
				</NcActionButton>
				<NcActionRadio v-for="(so, soId) in TRACK_SORT_ORDER"
					:key="soId"
					name="sortOrder"
					:checked="directory.sortOrder === so.value"
					@change="onSortOrderChange(so.value)">
					{{ so.label }}
				</NcActionRadio>
				<NcActionSeparator />
				<NcActionRadio
					name="sortAsc"
					:checked="directory.sortAsc === true"
					@change="onSortAscChange(true)">
					⬇ {{ t('gpxpod', 'Sort ascending') }}
				</NcActionRadio>
				<NcActionRadio
					name="sortAsc"
					:checked="directory.sortAsc !== true"
					@change="onSortAscChange(false)">
					⬆ {{ t('gpxpod', 'Sort descending') }}
				</NcActionRadio>
			</template>
			<template v-else-if="extraActionsOpen && !isPublicPage">
				<NcActionButton :close-after-click="false"
					@click="extraActionsOpen = false">
					<template #icon>
						<ChevronLeft :size="20" />
					</template>
					{{ t('gpxpod', 'Back') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="$emit('reload', directory.id)">
					<template #icon>
						<RefreshIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Reload') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="$emit('reload-reprocess')">
					<template #icon>
						<CogRefreshIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Reload and reprocess') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onDeleteSelectedTracksClick">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Delete selected tracks') }}
				</NcActionButton>
			</template>
			<template v-else-if="!isPublicPage">
				<NcActionButton
					:close-after-click="true"
					@click="$emit('details-click')">
					<template #icon>
						<InformationOutlineIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Details') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="$emit('share-click')">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Share') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onToggleAllClick">
					<template #icon>
						<ToggleSwitch v-if="allTracksSelected" :size="20" />
						<ToggleSwitchOffOutline v-else :size="20" />
					</template>
					{{ t('gpxpod', 'Toggle all') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="$emit('zoom')">
					<template #icon>
						<MagnifyExpand :size="20" />
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
				<NcActionButton :close-after-click="false"
					@click="sortActionsOpen = true">
					<template #icon>
						<SortAscending :size="20" />
					</template>
					{{ t('gpxpod', 'Change track sort order') }}
				</NcActionButton>
				<NcActionButton :close-after-click="false"
					@click="extraActionsOpen = true">
					<template #icon>
						<DotsHorizontalIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Other actions') }}
				</NcActionButton>
				<NcActionButton v-if="true"
					:close-after-click="true"
					@click="$emit('remove')">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Remove') }}
				</NcActionButton>
			</template>
		</template>
		<template #default>
			<NcAppNavigationItem v-if="Object.keys(directory.tracks).length === 0"
				:title="t('gpxpod', 'No track to show')">
				<template #icon>
					<GpxpodIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<AppNavigationTrackItem v-for="track in sortedTracks"
				:key="track.id"
				:track="track"
				@click="$emit('track-clicked', { trackId: track.id, dirId: directory.id })"
				@details-click="$emit('track-details-click', { trackId: track.id, dirId: directory.id })"
				@share-click="$emit('track-share-click', { trackId: track.id, dirId: directory.id })"
				@color-changed="$emit('track-color-changed', { trackId: track.id, dirId: directory.id, color: $event })"
				@criteria-changed="$emit('track-criteria-changed', { trackId: track.id, dirId: directory.id, value: $event })"
				@correct-elevations="$emit('track-correct-elevations', { trackId: track.id, dirId: directory.id })"
				@hover-in="$emit('track-hover-in', { trackId: track.id, dirId: directory.id })"
				@hover-out="$emit('track-hover-out', { trackId: track.id, dirId: directory.id })" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import RefreshIcon from 'vue-material-design-icons/Refresh.vue'
import CogRefreshIcon from 'vue-material-design-icons/CogRefresh.vue'
import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import ToggleSwitch from 'vue-material-design-icons/ToggleSwitch.vue'
import ToggleSwitchOffOutline from 'vue-material-design-icons/ToggleSwitchOffOutline.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import MagnifyExpand from 'vue-material-design-icons/MagnifyExpand.vue'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import SortAscending from 'vue-material-design-icons/SortAscending.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'

import GpxpodIcon from './icons/GpxpodIcon.vue'

import ClickOutside from 'vue-click-outside'
import AppNavigationTrackItem from './AppNavigationTrackItem.vue'

import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionRadio from '@nextcloud/vue/dist/Components/NcActionRadio.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'

import { dirname, basename } from '@nextcloud/paths'
import { generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'

import { TRACK_SORT_ORDER } from '../constants.js'
import { strcmp } from '../utils.js'

export default {
	name: 'AppNavigationDirectoryItem',
	components: {
		GpxpodIcon,
		AppNavigationTrackItem,
		NcAppNavigationItem,
		NcActionButton,
		NcActionLink,
		NcActionRadio,
		NcActionSeparator,
		FolderIcon,
		FolderOutlineIcon,
		ShareVariantIcon,
		DeleteIcon,
		ChevronLeft,
		SortAscending,
		MagnifyExpand,
		DownloadIcon,
		ToggleSwitch,
		ToggleSwitchOffOutline,
		InformationOutlineIcon,
		DotsHorizontalIcon,
		RefreshIcon,
		CogRefreshIcon,
	},
	directives: {
		ClickOutside,
	},
	inject: ['isPublicPage'],
	props: {
		directory: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			menuOpen: false,
			sortActionsOpen: false,
			TRACK_SORT_ORDER,
			extraActionsOpen: false,
		}
	},
	computed: {
		directoryName() {
			return basename(this.directory.path)
		},
		downloadLink() {
			return generateUrl(
				'/apps/files/ajax/download.php?dir={dir}&files={files}',
				{ dir: dirname(this.directory.path), files: this.directoryName }
			)
		},
		allTracksSelected() {
			let allSelected = true
			Object.values(this.directory.tracks).every(track => {
				if (!track.isEnabled) {
					allSelected = false
					return false
				}
				return true
			})
			return allSelected
		},
		sortedTracks() {
			if (this.directory.sortOrder === TRACK_SORT_ORDER.name.value) {
				const sortFunction = this.directory.sortAsc
					? (ta, tb) => {
						return strcmp(ta.name, tb.name)
					}
					: (ta, tb) => {
						return strcmp(tb.name, ta.name)
					}
				return Object.values(this.directory.tracks).sort(sortFunction)
			}
			if (this.directory.sortOrder === TRACK_SORT_ORDER.date.value) {
				const sortFunction = this.directory.sortAsc
					? (ta, tb) => {
						const tsA = ta.date_begin
						const tsB = tb.date_begin
						return tsA > tsB
							? 1
							: tsA < tsB
								? -1
								: 0
					}
					: (ta, tb) => {
						const tsA = ta.date_begin
						const tsB = tb.date_begin
						return tsA < tsB
							? 1
							: tsA > tsB
								? -1
								: 0
					}
				return Object.values(this.directory.tracks).sort(sortFunction)
			}
			if (this.directory.sortOrder === TRACK_SORT_ORDER.distance.value) {
				const sortFunction = this.directory.sortAsc
					? (ta, tb) => {
						return ta.total_distance > tb.total_distance
							? 1
							: ta.total_distance < tb.total_distance
								? -1
								: 0
					}
					: (ta, tb) => {
						return ta.total_distance < tb.total_distance
							? 1
							: ta.total_distance > tb.total_distance
								? -1
								: 0
					}
				return Object.values(this.directory.tracks).sort(sortFunction)
			}
			if (this.directory.sortOrder === TRACK_SORT_ORDER.duration.value) {
				const sortFunction = this.directory.sortAsc
					? (ta, tb) => {
						return ta.total_duration > tb.total_duration
							? 1
							: ta.total_duration < tb.total_duration
								? -1
								: 0
					}
					: (ta, tb) => {
						return ta.total_duration < tb.total_duration
							? 1
							: ta.total_duration > tb.total_duration
								? -1
								: 0
					}
				return Object.values(this.directory.tracks).sort(sortFunction)
			}
			if (this.directory.sortOrder === TRACK_SORT_ORDER.elevationGain.value) {
				const sortFunction = this.directory.sortAsc
					? (ta, tb) => {
						return ta.positive_elevation_gain > tb.positive_elevation_gain
							? 1
							: ta.positive_elevation_gain < tb.positive_elevation_gain
								? -1
								: 0
					}
					: (ta, tb) => {
						return ta.positive_elevation_gain < tb.positive_elevation_gain
							? 1
							: ta.positive_elevation_gain > tb.positive_elevation_gain
								? -1
								: 0
					}
				return Object.values(this.directory.tracks).sort(sortFunction)
			}
			return Object.values(this.directory.tracks)
		},
	},
	beforeMount() {
	},
	methods: {
		onDirectoryClick() {
			if (this.directory.isOpen) {
				this.$emit('close')
			} else {
				this.$emit('open')
			}
		},
		onDirectoryOpen(newOpen) {
			if (newOpen) {
				this.$emit('open')
			} else {
				this.$emit('close')
			}
		},
		onDetailClick() {
		},
		onShareClick() {
		},
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.sortActionsOpen = false
				this.extraActionsOpen = false
			}
			this.menuOpen = isOpen
		},
		onSortOrderChange(sortOrder) {
			this.$emit('sort-changed', { sortOrder })
		},
		onSortAscChange(sortAsc) {
			this.$emit('sort-changed', { sortAsc })
		},
		onToggleAllClick() {
			if (this.allTracksSelected) {
				Object.values(this.directory.tracks).forEach(track => {
					if (track.isEnabled) {
						this.$emit('track-clicked', {
							trackId: track.id,
							dirId: this.directory.id,
						})
					}
				})
			} else {
				Object.values(this.directory.tracks).forEach(track => {
					if (!track.isEnabled) {
						this.$emit('track-clicked', {
							trackId: track.id,
							dirId: this.directory.id,
						})
					}
				})
			}
		},
		onDeleteSelectedTracksClick() {
			const selectedTrackIds = Object.keys(this.directory.tracks).filter(trackId => {
				return this.directory.tracks[trackId].isEnabled
			})
			emit('delete-selected-tracks', { dirId: this.directory.id, trackIds: selectedTrackIds })
		},
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
