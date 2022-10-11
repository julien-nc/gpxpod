<template>
	<NcAppSidebar v-show="show"
		:title="title"
		:compact="true"
		:background="backgroundImageUrl"
		:subtitle="subtitle"
		:active="activeTab"
		@update:active="$emit('update:active', $event)"
		@close="$emit('close')">
		<!--template #description /-->
		<NcAppSidebarTab
			id="track-share"
			:name="t('gpxpod', 'Sharing')"
			:order="1">
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
			<SharingSidebarTab
				:path="track.trackpath" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="track-details"
			:name="t('gpxpod', 'Stats')"
			:order="2">
			<template #icon>
				<TableLargeIcon :size="20" />
			</template>
			<TrackDetailsSidebarTab
				:track="track" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="track-charts"
			:name="t('gpxpod', 'Charts')"
			:order="3">
			<template #icon>
				<ChartLineIcon :size="20" />
			</template>
			<TrackChartsSidebarTab
				:track="track"
				:active="activeTab === 'track-charts'"
				:settings="settings" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'
import TableLargeIcon from 'vue-material-design-icons/TableLarge.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'

import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'

import { imagePath } from '@nextcloud/router'
import TrackDetailsSidebarTab from './TrackDetailsSidebarTab.vue'
import TrackChartsSidebarTab from './TrackChartsSidebarTab.vue'
import SharingSidebarTab from './SharingSidebarTab.vue'

export default {
	name: 'TrackSidebar',
	components: {
		SharingSidebarTab,
		TrackDetailsSidebarTab,
		TrackChartsSidebarTab,
		NcAppSidebar,
		NcAppSidebarTab,
		ShareVariantIcon,
		TableLargeIcon,
		ChartLineIcon,
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
