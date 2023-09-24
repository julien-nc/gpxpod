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
					<NcAppNavigationItem v-if="!isPublicPage"
						:name="t('gpxpod', 'Old interface')"
						:href="oldInterfaceUrl">
						<template #icon>
							<RewindIcon class="icon" :size="20" />
						</template>
					</NcAppNavigationItem>
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
import RewindIcon from 'vue-material-design-icons/Rewind.vue'

import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import NavigationDirectoryItem from './NavigationDirectoryItem.vue'

import { emit } from '@nextcloud/event-bus'
import { dirname } from '@nextcloud/paths'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Navigation',

	components: {
		NavigationDirectoryItem,
		NcAppNavigationItem,
		NcAppNavigation,
		NcActionButton,
		PlusIcon,
		CogIcon,
		RewindIcon,
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
			oldInterfaceUrl: generateUrl('/apps/gpxpod/old-ui'),
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
			OC.dialogs.filepicker(
				t('gpxpod', 'Add directory'),
				(path) => {
					emit('directory-add', path)
					this.lastBrowsePath = dirname(path)
				},
				false,
				'httpd/unix-directory',
				true,
				undefined,
				this.lastBrowsePath,
			)
		},
		onAddDirectoryRecursiveClick() {
			OC.dialogs.filepicker(
				t('gpxpod', 'Recursively add a directory'),
				(path) => {
					emit('directory-add-recursive', path)
					this.lastBrowsePath = dirname(path)
				},
				false,
				'httpd/unix-directory',
				true,
				undefined,
				this.lastBrowsePath,
			)
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

	:deep(.app-navigation-toggle) {
		top: 0px !important;
		right: 0px !important;

		color: var(--color-main-text) !important;
		background-color: var(--color-main-background) !important;

		&:focus,
		&:hover {
			background-color: var(--color-background-hover) !important;
		}
	}

	&.compact :deep(.app-navigation-toggle) {
		margin-right: -54px !important;
		top: 6px !important;
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
