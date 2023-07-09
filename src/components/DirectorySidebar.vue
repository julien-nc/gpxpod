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
		class="directory-sidebar"
		@update:active="$emit('update:active', $event)"
		@close="$emit('close')">
		<!--template #description /-->
		<NcAppSidebarTab v-if="!isPublicPage"
			id="directory-share"
			:name="t('gpxpod', 'Sharing')"
			:order="1">
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
			<SharingSidebarTab
				:path="directory.path" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="directory-details"
			:name="t('gpxpod', 'Stats')"
			:order="2">
			<template #icon>
				<TableLargeIcon :size="20" />
			</template>
			<DirectoryDetailsSidebarTab
				ref="directoryDetailsTab"
				:directory="directory"
				:settings="settings" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import TableLargeIcon from 'vue-material-design-icons/TableLarge.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'

import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'

import { generateUrl } from '@nextcloud/router'
import { basename } from '@nextcloud/paths'
import DirectoryDetailsSidebarTab from './DirectoryDetailsSidebarTab.vue'
import SharingSidebarTab from './SharingSidebarTab.vue'

export default {
	name: 'DirectorySidebar',
	components: {
		SharingSidebarTab,
		DirectoryDetailsSidebarTab,
		NcAppSidebar,
		NcAppSidebarTab,
		ShareVariantIcon,
		TableLargeIcon,
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
		directory: {
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
			return generateUrl('/apps/theming/img/core/filetypes/folder.svg?v=' + (window.OCA?.Theming?.cacheBuster || 0))
		},
		pageIsPublic() {
			return false
		},
		title() {
			return basename(this.directory.path)
		},
		subtitle() {
			return this.directory.path
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
.directory-sidebar {
	font-size: var(--font-size) !important;
}
</style>
