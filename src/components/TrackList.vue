<template>
	<NcAppContentList>
		<div class="list-header">
			<NcAppNavigationItem
				class="headerItem"
				:title="directoryName">
				<template #icon>
					<FolderIcon />
				</template>
			</NcAppNavigationItem>
		</div>
		<NcEmptyContent v-if="tracks.length === 0 && !directory.loading"
			:title="t('gpxpod', 'No tracks')">
			<template #icon>
				<GpxpodIcon />
			</template>
		</NcEmptyContent>
		<h2 v-show="directory.loading"
			class="icon-loading-small loading-icon" />
		<TrackListItem
			v-for="(track, index) in sortedTracks"
			:key="track.id"
			:track="track"
			:settings="settings"
			:index="nbTracks - index"
			:count="nbTracks"
			:selected="isTrackSelected(track)" />
	</NcAppContentList>
</template>

<script>
import FolderIcon from 'vue-material-design-icons/Folder.vue'

import GpxpodIcon from './icons/GpxpodIcon.vue'
import TrackListItem from './TrackListItem.vue'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcAppContentList from '@nextcloud/vue/dist/Components/NcAppContentList.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'

import { basename } from '@nextcloud/paths'

import { sortTracks } from '../utils.js'

export default {
	name: 'TrackList',

	components: {
		TrackListItem,
		GpxpodIcon,
		NcAppContentList,
		NcEmptyContent,
		NcAppNavigationItem,
		FolderIcon,
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
		directoryName() {
			return basename(this.directory.path)
		},
		tracks() {
			return Object.values(this.directory.tracks)
		},
		nbTracks() {
			return this.tracks.length
		},
		sortedTracks() {
			return sortTracks(Object.values(this.directory.tracks), this.directory.sortOrder, this.directory.sortAsc)
		},
	},

	watch: {
	},

	methods: {
		isTrackSelected(track) {
			return false
		},
	},
}
</script>

<style scoped lang="scss">
.list-header {
	position: sticky;
	top: 0;
	z-index: 1000;
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);

	.headerItem {
		padding-left: 40px;
	}
}
</style>