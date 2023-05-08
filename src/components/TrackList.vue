<template>
	<NcAppContentList>
		<div class="list-header">
			header
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

import GpxpodIcon from './icons/GpxpodIcon.vue'
import TrackListItem from './TrackListItem.vue'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcAppContentList from '@nextcloud/vue/dist/Components/NcAppContentList.js'
import { sortTracks } from '../utils.js'

export default {
	name: 'TrackList',

	components: {
		TrackListItem,
		GpxpodIcon,
		NcAppContentList,
		NcEmptyContent,
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
// nothing yet
</style>
