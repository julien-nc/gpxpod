<template>
	<NcAppSidebar v-show="show"
		:name="title"
		:title="title"
		:compact="true"
		:background="backgroundImageUrl"
		:subname="subtitle"
		:subtitle="subtitle"
		:active="activeTab"
		:style="cssVars"
		class="track-sidebar"
		@update:active="$emit('update:active', $event)"
		@close="$emit('close')">
		<!--template #description /-->
		<NcAppSidebarTab v-if="!isPublicPage"
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
				:track="track"
				:settings="settings" />
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
	inject: ['isPublicPage'],
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
		cssVars() {
			return {
				'--font-size': this.settings.fontScale + '%',
			}
		},
	},
	methods: {
	},
}
</script>

<style lang="scss" scoped>
.track-sidebar {
	font-size: var(--font-size) !important;
}

::v-deep .app-sidebar-header__figure {
	filter: var(--background-invert-if-dark);
}
</style>
