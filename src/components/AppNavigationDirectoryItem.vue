<template>
	<AppNavigationItem
		:title="directoryName"
		:class="{ openDirectory: directory.isOpen }"
		:allow-collapse="true"
		:open="directory.isOpen"
		:force-menu="false"
		@click="onDirectoryClick"
		@update:open="onDirectoryOpen">
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
			<ActionButton v-if="true"
				:close-after-click="true"
				@click="onRemoveDirectoryClick">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('gpxpod', 'Remove') }}
			</ActionButton>
		</template>
		<template #default>
			<AppNavigationItem v-if="Object.keys(directory.tracks).length === 0"
				:title="t('gpxpod', 'No track found')">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
			</AppNavigationItem>
			<AppNavigationTrackItem v-for="(track, trackId) in directory.tracks"
				:key="trackId"
				:track="track"
				:enabled="track.enabled"
				@click="$emit('track-clicked', { trackId: track.id, path })"
				@delete="onDeleteTrack(track.id, path)"
				@edited="onEditTrack(track.id, path)"
				@color-changed="$emit('track-color-changed', { trackId: track.id, path, color: $event })"
				@criteria-changed="$emit('track-criteria-changed', { trackId: track.id, path, criteria: $event })"
				@hover-in="$emit('track-hover-in', { trackId: track.id, path })"
				@hover-out="$emit('track-hover-out', { trackId: track.id, path })" />
		</template>
	</AppNavigationItem>
</template>

<script>
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import CogIcon from 'vue-material-design-icons/Cog'
import PlusIcon from 'vue-material-design-icons/Plus'
import DeleteIcon from 'vue-material-design-icons/Delete'
import FolderIcon from 'vue-material-design-icons/Folder'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline'
import ClickOutside from 'vue-click-outside'
import AppNavigationTrackItem from './AppNavigationTrackItem'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import { basename } from '@nextcloud/paths'

export default {
	name: 'AppNavigationDirectoryItem',
	components: {
		AppNavigationTrackItem,
		AppNavigationItem,
		ActionButton,
		FolderIcon,
		FolderOutlineIcon,
		CogIcon,
		ShareVariantIcon,
		PlusIcon,
		DeleteIcon,
	},
	directives: {
		ClickOutside,
	},
	props: {
		directory: {
			type: Object,
			required: true,
		},
		path: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
		}
	},
	computed: {
		directoryName() {
			return basename(this.path)
		},
	},
	beforeMount() {
	},
	methods: {
		onDirectoryClick() {
			if (this.directory.isOpen) {
				this.$emit('close', this.path)
			} else {
				this.$emit('open', this.path)
			}
		},
		onDirectoryOpen(newOpen) {
			if (newOpen) {
				this.$emit('open', this.path)
			} else {
				this.$emit('close', this.path)
			}
		},
		onRemoveDirectoryClick() {
			this.$emit('remove', this.path)
		},
		onTrackClick(trackId) {
		},
		onDetailClick() {
		},
		onShareClick() {
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
