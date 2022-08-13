<template>
	<AppSidebar v-show="show"
		:title="title"
		:compact="true"
		:background="backgroundImageUrl"
		:subtitle="subtitle"
		:active="activeTab"
		@update:active="$emit('update:active', $event)"
		@close="$emit('close')">
		<!--template #description /-->
		<AppSidebarTab
			id="track-share"
			:name="t('gpxpod', 'Sharing')"
			:order="1">
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
			share track
			<!--SharingTabSidebar
				:project="project"
				@project-edited="onProjectEdited" /-->
		</AppSidebarTab>
		<AppSidebarTab
			id="track-details"
			:name="t('gpxpod', 'Stats')"
			:order="2">
			<template #icon>
				<TableLargeIcon :size="20" />
			</template>
			details track
			<TrackDetailsSidebarTab
				ref="trackDetailsTab"
				:track="track" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import TableLargeIcon from 'vue-material-design-icons/TableLarge'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'

import { imagePath } from '@nextcloud/router'
import TrackDetailsSidebarTab from './TrackDetailsSidebarTab'

export default {
	name: 'TrackSidebar',
	components: {
		TrackDetailsSidebarTab,
		AppSidebar,
		AppSidebarTab,
		ShareVariantIcon,
		TableLargeIcon,
	},
	props: {
		show: {
			type: Boolean,
			required: true,
		},
		activeTab: {
			type: String,
			required: true,
		},
		track: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
		}
	},
	computed: {
		backgroundImageUrl() {
			return imagePath('gpxpod', 'app_black.svg')
		},
		pageIsPublic() {
			return false
		},
		title() {
			return this.track.name
		},
		subtitle() {
			return this.track.trackpath
		},
	},
	methods: {
	},
}
</script>

<style lang="scss" scoped>
::v-deep .app-sidebar-header__figure {
	filter: var(--background-invert-if-dark);
}
</style>
