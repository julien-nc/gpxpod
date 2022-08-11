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
					class="detailButton"
					@click="onDetailClick">
					<template #icon>
						<CogIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Details') }}
				</ActionButton>
				<ActionButton
					class="detailButton"
					@click="onShareClick">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Share') }}
				</ActionButton>
				<ActionButton
					:close-after-click="true"
					@click="onZoomClick">
					<template #icon>
						<MagnifyExpand :size="20" />
					</template>
					{{ t('gpxpod', 'Zoom to bounds') }}
				</ActionButton>
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
				@color-changed="$emit('track-color-changed', { trackId: track.id, dirId: directory.id, color: $event })"
				@criteria-changed="$emit('track-criteria-changed', { trackId: track.id, dirId: directory.id, criteria: $event })"
				@hover-in="$emit('track-hover-in', { trackId: track.id, dirId: directory.id })"
				@hover-out="$emit('track-hover-out', { trackId: track.id, dirId: directory.id })" />
		</template>
	</AppNavigationItem>
</template>

<script>
import MagnifyExpand from 'vue-material-design-icons/MagnifyExpand'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import CogIcon from 'vue-material-design-icons/Cog'
import SortAscending from 'vue-material-design-icons/SortAscending'
import DeleteIcon from 'vue-material-design-icons/Delete'
import FolderIcon from 'vue-material-design-icons/Folder'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline'
import ClickOutside from 'vue-click-outside'
import AppNavigationTrackItem from './AppNavigationTrackItem'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionRadio from '@nextcloud/vue/dist/Components/ActionRadio'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import { basename } from '@nextcloud/paths'
import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import GpxpodIcon from './icons/GpxpodIcon'

import { TRACK_SORT_ORDER } from '../constants'
import { strcmp } from '../utils'

export default {
	name: 'AppNavigationDirectoryItem',
	components: {
		GpxpodIcon,
		AppNavigationTrackItem,
		AppNavigationItem,
		ActionButton,
		ActionRadio,
		FolderIcon,
		FolderOutlineIcon,
		CogIcon,
		ShareVariantIcon,
		DeleteIcon,
		ChevronLeft,
		SortAscending,
		MagnifyExpand,
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
	},
}
</script>

<style scoped lang="scss">
::v-deep .detailButton {
	border-radius: 50%;
	&:hover {
		background-color: var(--color-background-darker);
	}
	button {
		padding-right: 0 !important;
		border-radius: 50%;
	}
}
</style>
