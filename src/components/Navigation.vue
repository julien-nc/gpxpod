<template>
	<NcAppNavigation ref="nav"
		:class="{ gpxpodNavigation: true, compact }">
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
				@remove="$emit('directory-remove', dirId)"
				@sort-changed="$emit('directory-sort-changed', { dirId, ...$event })"
				@details-click="$emit('directory-details-click', dirId)"
				@share-click="$emit('directory-share-click', dirId)"
				@hover-in="$emit('directory-hover-in', dirId)"
				@hover-out="$emit('directory-hover-out', dirId)" />
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

import { emit } from '@nextcloud/event-bus'
import { dirname } from '@nextcloud/paths'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import NavigationDirectoryItem from './NavigationDirectoryItem.vue'

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
	},

	data() {
		return {
			addMenuOpen: false,
			lastBrowsePath: null,
		}
	},

	computed: {
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
			OC.dialogs.filepicker(
				t('gpxpod', 'Add directory'),
				(path) => {
					this.$emit('directory-add', path)
					this.lastBrowsePath = dirname(path)
				},
				false,
				'httpd/unix-directory',
				true,
				undefined,
				this.lastBrowsePath
			)
		},
		onAddDirectoryRecursiveClick() {
			OC.dialogs.filepicker(
				t('gpxpod', 'Recursively add a directory'),
				(path) => {
					this.$emit('directory-add-recursive', path)
					this.lastBrowsePath = dirname(path)
				},
				false,
				'httpd/unix-directory',
				true,
				undefined,
				this.lastBrowsePath
			)
		},
	},
}
</script>

<style scoped lang="scss">
.gpxpodNavigation {
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

	&.compact :deep(.app-navigation-toggle) {
		margin-right: -54px !important;
		top: 6px !important;
	}

	:deep(.app-navigation-toggle) {
		color: var(--color-main-text) !important;
		background-color: var(--color-main-background) !important;

		&:focus,
		&:hover {
			background-color: var(--color-background-hover) !important;
		}
	}

	:deep(.trackItem) {
		&.selectedTrack .app-navigation-entry {
			background: var(--color-primary-light, lightgrey);

			> a {
				font-weight: bold;
			}
		}
	}
}
</style>
