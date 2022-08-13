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
			id="directory-share"
			:name="t('gpxpod', 'Sharing')"
			:order="1">
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
			share dir
			<!--SharingTabSidebar
				:project="project"
				@project-edited="onProjectEdited" /-->
		</AppSidebarTab>
		<AppSidebarTab
			id="directory-details"
			:name="t('gpxpod', 'Stats')"
			:order="2">
			<template #icon>
				<TableLargeIcon :size="20" />
			</template>
			<DirectoryDetailsSidebarTab
				ref="directoryDetailsTab"
				:directory="directory" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import TableLargeIcon from 'vue-material-design-icons/TableLarge'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'

import { generateUrl } from '@nextcloud/router'
import { basename } from '@nextcloud/paths'
import DirectoryDetailsSidebarTab from './DirectoryDetailsSidebarTab'

export default {
	name: 'DirectorySidebar',
	components: {
		DirectoryDetailsSidebarTab,
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
		directory: {
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
	},
	methods: {
	},
}
</script>

<style lang="scss" scoped>
// nothing yet
</style>
