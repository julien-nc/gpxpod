<template>
	<AppNavigationItem
		:title="path"
		:class="{ openDirectory: directory.isOpen }"
		:allow-collapse="true"
		:open="directory.isOpen"
		:force-menu="false"
		@click="onDirectoryClick">
		<template #icon>
			<FolderIcon v-if="directory.isOpen"
				:size="20" />
			<FolderOutlineIcon v-else
				:size="20" />
		</template>
		<template #counter>
			<Actions>
				<ActionButton
					class="detailButton"
					@click="onShareClick">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
				</ActionButton>
			</Actions>
			<Actions>
				<ActionButton
					class="detailButton"
					@click="onDetailClick">
					<template #icon>
						<CogIcon :size="20" />
					</template>
				</ActionButton>
			</Actions>
		</template>
		<template #actions>
			<ActionButton v-if="true"
				:close-after-click="true"
				@click="onDeleteDirectoryClick">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('gpxpod', 'Delete') }}
			</ActionButton>
		</template>
		<template #default>
			<AppNavigationItem v-if="Object.keys(directory.tracks).length === 0"
				:title="t('gpxpod', 'No track found')">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
			</AppNavigationItem>
			<!--AppNavigationTrackItem v-for="(track, trackId) in directory.tracks"
				:key="trackId"
				class="trackItem"
				:track="track"
				:selected="track.selected"
				@click="onTrackClick(trackId)" /-->
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
// import AppNavigationTrackItem from './AppNavigationTrackItem'

import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'

export default {
	name: 'AppNavigationDirectoryItem',
	components: {
		// AppNavigationTrackItem,
		AppNavigationItem,
		ActionButton,
		Actions,
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
	},
	beforeMount() {
	},
	methods: {
		onDirectoryClick() {
		},
		onDeleteDirectoryClick() {
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
.trackItem {
	height: 44px;
	padding-left: 30px !important;
	padding-right: 0 !important;
}

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
