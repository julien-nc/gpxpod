<template>
	<NcAppNavigationItem
		:name="directoryName"
		:title="directoryItemTitle"
		:class="{ openDirectory: directory.isOpen }"
		:active="selected"
		:loading="directory.loading"
		:allow-collapse="compact"
		:open="directory.isOpen"
		:force-menu="true"
		:force-display-actions="true"
		:menu-open="menuOpen"
		@click="onItemClick"
		@update:open="onUpdateOpen"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@update:menuOpen="onUpdateMenuOpen"
		@mouseenter.native="onHoverIn"
		@mouseleave.native="onHoverOut">
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
				<NcActionLink
					:close-after-click="true"
					:href="downloadLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Download') }}
				</NcActionLink>
				<NcActionLink
					key="downloadKmlLink"
					:close-after-click="true"
					:href="downloadKmlLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Download as KML') }}
				</NcActionLink>
				<NcActionLink
					key="downloadKmzLink"
					:close-after-click="true"
					:href="downloadKmzLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Download as KMZ (with photos)') }}
				</NcActionLink>
				<NcActionCheckbox
					:close-after-click="true"
					:checked="directory.recursive"
					@change="onChangeRecursive">
					{{ t('gpxpod', 'Display recursively') }}
				</NcActionCheckbox>
				<NcActionButton
					:close-after-click="true"
					@click="onCompareSelectedTracksClick">
					<template #icon>
						<ScaleBalanceIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Compare selected tracks') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onReload">
					<template #icon>
						<RefreshIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Reload') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onReloadReprocess">
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
					@click="onDetailsClick">
					<template #icon>
						<InformationOutlineIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Details') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onShareClick">
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
					@click="onZoomToBounds">
					<template #icon>
						<MagnifyExpand :size="20" />
					</template>
					{{ t('gpxpod', 'Zoom to bounds') }}
				</NcActionButton>
				<NcActionButton :close-after-click="false"
					@click="sortActionsOpen = true">
					<template #icon>
						<SortAscending :size="20" />
					</template>
					{{ t('gpxpod', 'Change track sort order') }}
				</NcActionButton>
				<NcActionButton v-if="true"
					:close-after-click="true"
					@click="onRemove">
					<template #icon>
						<FolderOffOutlineIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Remove from list') }}
				</NcActionButton>
				<NcActionButton :close-after-click="false"
					@click="extraActionsOpen = true">
					<template #icon>
						<DotsHorizontalIcon :size="20" />
					</template>
					{{ t('gpxpod', 'More actions') }}
				</NcActionButton>
			</template>
		</template>
		<template #default>
			<NcAppNavigationItem v-if="compact && Object.keys(directory.tracks).length === 0"
				:name="t('gpxpod', 'No track to show')">
				<template #icon>
					<GpxpodIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<NavigationTrackItem v-for="track in sortedTracks"
				:key="track.id"
				:track="track" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import ScaleBalanceIcon from 'vue-material-design-icons/ScaleBalance.vue'
import FolderOffOutlineIcon from 'vue-material-design-icons/FolderOffOutline.vue'
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
import NavigationTrackItem from './NavigationTrackItem.vue'

import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionRadio from '@nextcloud/vue/dist/Components/NcActionRadio.js'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'

import { dirname, basename } from '@nextcloud/paths'
import { generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'

import { TRACK_SORT_ORDER } from '../constants.js'
import { sortTracks } from '../utils.js'

export default {
	name: 'NavigationDirectoryItem',
	components: {
		GpxpodIcon,
		NavigationTrackItem,
		NcAppNavigationItem,
		NcActionButton,
		NcActionLink,
		NcActionRadio,
		NcActionCheckbox,
		NcActionSeparator,
		FolderIcon,
		FolderOutlineIcon,
		ShareVariantIcon,
		DeleteIcon,
		ScaleBalanceIcon,
		FolderOffOutlineIcon,
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
		compact: {
			type: Boolean,
			default: false,
		},
		selected: {
			type: Boolean,
			default: false,
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
		directoryItemTitle() {
			const tracks = this.directory.tracks
			const nbTracks = Object.keys(tracks).length
			const photos = this.directory.pictures
			const nbPhotos = Object.keys(photos).length
			return this.directory.path
				+ (nbTracks > 0
					? '\n' + n('gpxpod', '{n} track', '{n} tracks', nbTracks, { n: nbTracks })
					: '')
				+ (nbPhotos > 0
					? '\n' + n('gpxpod', '{np} photo', '{np} photos', nbPhotos, { np: nbPhotos })
					: '')
		},
		downloadLink() {
			return generateUrl(
				'/apps/files/ajax/download.php?dir={dir}&files={files}',
				{ dir: dirname(this.directory.path), files: this.directoryName },
			)
		},
		downloadKmlLink() {
			return generateUrl(
				'/apps/gpxpod/directories/{dirId}/kml',
				{ dirId: this.directory.id },
			)
		},
		downloadKmzLink() {
			return generateUrl(
				'/apps/gpxpod/directories/{dirId}/kmz',
				{ dirId: this.directory.id },
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
			if (!this.compact) {
				return []
			}
			return sortTracks(Object.values(this.directory.tracks), this.directory.sortOrder, this.directory.sortAsc)
		},
	},
	beforeMount() {
	},
	methods: {
		onItemClick() {
			emit('directory-click', this.directory.id)
		},
		onUpdateOpen(newOpen) {
			if (newOpen) {
				emit('directory-open', this.directory.id)
			} else {
				emit('directory-close', this.directory.id)
			}
		},
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.sortActionsOpen = false
				this.extraActionsOpen = false
			}
			this.menuOpen = isOpen
		},
		onSortOrderChange(sortOrder) {
			emit('directory-sort-changed', { dirId: this.directory.id, sortOrder })
		},
		onSortAscChange(sortAsc) {
			emit('directory-sort-changed', { dirId: this.directory.id, sortAsc })
		},
		onZoomToBounds() {
			emit('directory-zoom', this.directory.id)
		},
		onToggleAllClick() {
			if (this.allTracksSelected) {
				Object.values(this.directory.tracks).filter(t => t.isEnabled).forEach(track => {
					emit('track-clicked', { trackId: track.id, dirId: track.directoryId })
				})
			} else {
				Object.values(this.directory.tracks).filter(t => !t.isEnabled).forEach(track => {
					emit('track-clicked', { trackId: track.id, dirId: track.directoryId })
				})
			}
		},
		onCompareSelectedTracksClick() {
			const selectedTrackIds = Object.keys(this.directory.tracks).filter(trackId => {
				return this.directory.tracks[trackId].isEnabled
			})
			emit('compare-selected-tracks', { dirId: this.directory.id, trackIds: selectedTrackIds })
		},
		onDeleteSelectedTracksClick() {
			const selectedTrackIds = Object.keys(this.directory.tracks).filter(trackId => {
				return this.directory.tracks[trackId].isEnabled
			})
			emit('delete-selected-tracks', { dirId: this.directory.id, trackIds: selectedTrackIds })
		},
		onChangeRecursive() {
			emit('directory-recursive-changed', this.directory.id)
		},
		onReloadReprocess() {
			emit('directory-reload-reprocess', this.directory.id)
		},
		onReload() {
			emit('directory-reload', this.directory.id)
		},
		onDetailsClick() {
			emit('directory-details-click', this.directory.id)
		},
		onShareClick() {
			emit('directory-share-click', this.directory.id)
		},
		onHoverIn() {
			emit('directory-hover-in', this.directory.id)
		},
		onHoverOut() {
			emit('directory-hover-out', this.directory.id)
		},
		onRemove() {
			emit('directory-remove', this.directory.id)
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
