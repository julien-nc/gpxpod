<template>
	<NcAppNavigation ref="nav"
		class="gpxpodNavigation"
		:class="{ compact }"
		:style="cssVars">
		<template #list>
			<NcAppNavigationItem v-if="!isPublicPage"
				:name="t('gpxpod', 'Add directories')"
				class="addDirItem"
				:menu-open="addMenuOpen"
				@click="addMenuOpen = true"
				@contextmenu.native.stop.prevent="addMenuOpen = true"
				@update:menuOpen="updateAddMenuOpen">
				<template #icon>
					<PlusIcon />
				</template>
				<template #actions>
					<NcActionButton
						:close-after-click="true"
						@click="onAddDirectoryClick">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Add one directory') }}
					</NcActionButton>
					<NcActionButton
						:close-after-click="true"
						@click="onAddDirectoryRecursiveClick">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Recursively add a directory') }}
					</NcActionButton>
				</template>
			</NcAppNavigationItem>
			<NavigationDirectoryItem v-for="(dir, dirId) in directories"
				:key="dirId"
				class="directoryItem"
				:directory="dir"
				:compact="compact"
				:selected="!compact && dir.id === selectedDirectoryId" />
		</template>
		<!--template #footer></template-->
		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<NcAppNavigationItem
						:name="t('gpxpod', 'GpxPod settings')"
						@click="showSettings">
						<template #icon>
							<CogIcon
								class="icon"
								:size="20" />
						</template>
					</NcAppNavigationItem>
				</div>
			</div>
		</template>
	</NcAppNavigation>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'

import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import NavigationDirectoryItem from './NavigationDirectoryItem.vue'

import { getFilePickerBuilder, FilePickerType } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { dirname } from '@nextcloud/paths'

export default {
	name: 'Navigation',

	components: {
		NavigationDirectoryItem,
		NcAppNavigationItem,
		NcAppNavigation,
		NcActionButton,
		PlusIcon,
		CogIcon,
	},

	inject: ['isPublicPage'],

	props: {
		directories: {
			type: Object,
			required: true,
		},
		compact: {
			type: Boolean,
			default: false,
		},
		selectedDirectoryId: {
			type: [String, Number],
			default: 0,
		},
		fontScale: {
			type: Number,
			default: 100,
		},
	},

	data() {
		return {
			addMenuOpen: false,
			lastBrowsePath: null,
		}
	},

	computed: {
		cssVars() {
			return {
				'--font-size': this.fontScale + '%',
			}
		},
	},

	watch: {
	},

	mounted() {
		const navToggleButton = this.$refs.nav.$el.querySelector('button.app-navigation-toggle')
		navToggleButton.addEventListener('click', (e) => {
			emit('nav-toggled')
		})
	},

	methods: {
		showSettings() {
			emit('show-settings')
		},
		updateAddMenuOpen(open) {
			if (!open) {
				this.addMenuOpen = false
			}
		},
		onAddDirectoryClick() {
			const picker = getFilePickerBuilder(t('gpxpod', 'Add directory'))
				.setMultiSelect(false)
				.setType(FilePickerType.Choose)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.startAt(this.lastBrowsePath)
				.build()
			picker.pick()
				.then(async (path) => {
					emit('directory-add', path)
					this.lastBrowsePath = dirname(path)
				})
		},
		onAddDirectoryRecursiveClick() {
			const picker = getFilePickerBuilder(t('gpxpod', 'Recursively add a directory'))
				.setMultiSelect(false)
				.setType(FilePickerType.Choose)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.startAt(this.lastBrowsePath)
				.build()
			picker.pick()
				.then(async (path) => {
					emit('directory-add-recursive', path)
					this.lastBrowsePath = dirname(path)
				})
		},
	},
}
</script>

<style scoped lang="scss">
.gpxpodNavigation {
	font-size: var(--font-size) !important;

	.addDirItem {
		position: sticky;
		top: 0;
		z-index: 1000;
		padding-right: 0 !important;

		:deep(.app-navigation-entry) {
			background-color: var(--color-main-background-blur, var(--color-main-background));
			backdrop-filter: var(--filter-background-blur, none);

			&:hover {
				background-color: var(--color-background-hover);
			}
		}
	}

	:deep(.app-navigation-toggle-wrapper) {
		top: 0px !important;
		right: 0px !important;

		.app-navigation-toggle {
			color: var(--color-main-text) !important;
			background-color: var(--color-main-background) !important;

			&:focus,
			&:hover {
				background-color: var(--color-background-hover) !important;
			}
		}
	}

	&.compact :deep(.app-navigation-toggle-wrapper) {
		margin-right: -54px !important;
		top: 6px !important;
	}
}
</style>
