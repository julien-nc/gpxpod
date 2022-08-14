<template>
	<AppNavigationItem
		:title="directoryName"
		:class="{ openDirectory: directory.isOpen }"
		:allow-collapse="true"
		:open="directory.isOpen"
		:force-menu="false"
		:menu-open="menuOpen"
		@click="onDirectoryClick"
		@update:open="onDirectoryOpen"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@update:menuOpen="onUpdateMenuOpen">
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
			<template v-if="!sortActionsOpen">
				<ActionButton
					:close-after-click="true"
					@click="$emit('directory-details-click', directory.id)">
					<template #icon>
						<InformationOutlineIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Details') }}
				</ActionButton>
				<ActionButton
					:close-after-click="true"
					@click="$emit('directory-share-click', directory.id)">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Share') }}
				</ActionButton>
				<ActionButton
					:close-after-click="true"
					@click="onToggleAllClick">
					<template #icon>
						<ToggleSwitch v-if="allTracksSelected" :size="20" />
						<ToggleSwitchOffOutline v-else :size="20" />
					</template>
					{{ t('gpxpod', 'Toggle all') }}
				</ActionButton>
				<ActionButton
					:close-after-click="true"
					@click="onZoomClick">
					<template #icon>
						<MagnifyExpand :size="20" />
					</template>
					{{ t('gpxpod', 'Zoom to bounds') }}
				</ActionButton>
				<ActionLink
					:close-after-click="true"
					:href="downloadLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Download') }}
				</ActionLink>
				<ActionButton :close-after-click="false"
					@click="sortActionsOpen = true">
					<template #icon>
						<SortAscending :size="20" />
					</template>
					{{ t('gpxpod', 'Change track sort order') }}
				</ActionButton>
				<ActionButton v-if="true"
					:close-after-click="true"
					@click="onRemoveDirectoryClick">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Remove') }}
				</ActionButton>
			</template>
			<template v-else>
				<ActionButton :close-after-click="false"
					@click="sortActionsOpen = false">
					<template #icon>
						<ChevronLeft :size="20" />
					</template>
					{{ t('gpxpod', 'Back') }}
				</ActionButton>
				<ActionRadio v-for="(so, soId) in TRACK_SORT_ORDER"
					:key="soId"
					name="sortOrder"
					:checked="directory.sortOrder === so.value"
					@change="onSortOrderChange(so.value)">
					{{ so.label }}
				</ActionRadio>
			</template>
		</template>
		<template #default>
			<AppNavigationItem v-if="Object.keys(directory.tracks).length === 0"
				:title="t('gpxpod', 'No track found')">
				<template #icon>
					<GpxpodIcon :size="20" />
				</template>
			</AppNavigationItem>
			<AppNavigationTrackItem v-for="track in sortedTracks"
				:key="track.id"
				:track="track"
				@click="$emit('track-clicked', { trackId: track.id, dirId: directory.id })"
				@delete="onDeleteTrack(track.id, directory.id)"
				@edited="onEditTrack(track.id, directory.id)"
				@details-click="$emit('track-details-click', { trackId: track.id, dirId: directory.id })"
				@share-click="$emit('track-share-click', { trackId: track.id, dirId: directory.id })"
				@color-changed="$emit('track-color-changed', { trackId: track.id, dirId: directory.id, color: $event })"
				@criteria-changed="$emit('track-criteria-changed', { trackId: track.id, dirId: directory.id, criteria: $event })"
				@hover-in="$emit('track-hover-in', { trackId: track.id, dirId: directory.id })"
				@hover-out="$emit('track-hover-out', { trackId: track.id, dirId: directory.id })" />
		</template>
	</AppNavigationItem>
</template>

<script>
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
import ClickOutside from 'vue-click-outside'
import AppNavigationTrackItem from './AppNavigationTrackItem.vue'

import ActionLink from '@nextcloud/vue/dist/Components/ActionLink.js'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton.js'
import ActionRadio from '@nextcloud/vue/dist/Components/ActionRadio.js'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem.js'
import { dirname, basename } from '@nextcloud/paths'
import { generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import GpxpodIcon from './icons/GpxpodIcon.vue'

import { TRACK_SORT_ORDER } from '../constants.js'
import { strcmp } from '../utils.js'

export default {
	name: 'AppNavigationDirectoryItem',
	components: {
		GpxpodIcon,
		AppNavigationTrackItem,
		AppNavigationItem,
		ActionButton,
		ActionLink,
		ActionRadio,
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
	},
	directives: {
		ClickOutside,
	},
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
				return Object.values(this.directory.tracks).sort((ta, tb) => {
					return strcmp(ta.name, tb.name)
				})
			}
			if (this.directory.sortOrder === TRACK_SORT_ORDER.date.value) {
				return Object.values(this.directory.tracks).sort((ta, tb) => {
					const tsA = moment(ta.date_begin).unix()
					const tsB = moment(tb.date_begin).unix()
					return tsA > tsB
						? 1
						: tsA < tsB
							? -1
							: 0
				})
			}
			if (this.directory.sortOrder === TRACK_SORT_ORDER.distance.value) {
				return Object.values(this.directory.tracks).sort((ta, tb) => {
					return ta.total_distance > tb.total_distance
						? 1
						: ta.total_distance < tb.total_distance
							? -1
							: 0
				})
			}
			if (this.directory.sortOrder === TRACK_SORT_ORDER.duration.value) {
				return Object.values(this.directory.tracks).sort((ta, tb) => {
					return ta.total_duration > tb.total_duration
						? 1
						: ta.total_duration < tb.total_duration
							? -1
							: 0
				})
			}
			if (this.directory.sortOrder === TRACK_SORT_ORDER.elevationGain.value) {
				return Object.values(this.directory.tracks).sort((ta, tb) => {
					return ta.positive_elevation_gain > tb.positive_elevation_gain
						? 1
						: ta.positive_elevation_gain < tb.positive_elevation_gain
							? -1
							: 0
				})
			}
			return Object.values(this.directory.tracks)
		},
	},
	beforeMount() {
	},
	methods: {
		onDirectoryClick() {
			if (this.directory.isOpen) {
				this.$emit('close', this.directory.id)
			} else {
				this.$emit('open', this.directory.id)
			}
		},
		onDirectoryOpen(newOpen) {
			if (newOpen) {
				this.$emit('open', this.directory.id)
			} else {
				this.$emit('close', this.directory.id)
			}
		},
		onRemoveDirectoryClick() {
			this.$emit('remove', this.directory.id)
		},
		onDetailClick() {
		},
		onShareClick() {
		},
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.sortActionsOpen = false
			}
			this.menuOpen = isOpen
		},
		onSortOrderChange(sortOrder) {
			this.$emit('sort-order-changed', sortOrder)
		},
		onZoomClick() {
			const tracksArray = Object.values(this.directory.tracks)
			if (tracksArray.length === 0) {
				return
			}
			let north = tracksArray[0].north
			let east = tracksArray[0].east
			let south = tracksArray[0].south
			let west = tracksArray[0].west
			for (let i = 1; i < tracksArray.length; i++) {
				const t = tracksArray[i]
				if (t.north > north) {
					north = t.north
				}
				if (t.south < south) {
					south = t.south
				}
				if (t.east > east) {
					east = t.east
				}
				if (t.west < west) {
					west = t.west
				}
			}
			emit('zoom-on', { north, south, east, west })
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
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
