<template>
	<NcAppNavigation ref="nav">
		<template #list>
			<NcAppNavigationItem v-if="!isPublicPage"
				:title="t('gpxpod', 'Add directories')"
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
			<AppNavigationDirectoryItem v-for="(dir, dirId) in directories"
				:key="dirId"
				class="directoryItem"
				:directory="dir"
				@open="$emit('directory-open', dirId)"
				@close="$emit('directory-close', dirId)"
				@remove="$emit('directory-remove', dirId)"
				@sort-changed="$emit('directory-sort-changed', { dirId, ...$event })"
				@details-click="$emit('directory-details-click', dirId)"
				@share-click="$emit('directory-share-click', dirId)"
				@hover-in="$emit('directory-hover-in', dirId)"
				@hover-out="$emit('directory-hover-out', dirId)"
				@reload="$emit('directory-reload', dirId)"
				@reload-reprocess="$emit('directory-reload-reprocess', dirId)"
				@track-clicked="$emit('track-clicked', $event)"
				@track-color-changed="$emit('track-color-changed', $event)"
				@track-criteria-changed="$emit('track-criteria-changed', $event)"
				@track-correct-elevations="$emit('track-correct-elevations', $event)"
				@track-details-click="$emit('track-details-click', $event)"
				@track-share-click="$emit('track-share-click', $event)"
				@track-hover-in="$emit('track-hover-in', $event)"
				@track-hover-out="$emit('track-hover-out', $event)" />
		</template>
		<!--template #footer></template-->
		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<NcAppNavigationItem
						:title="t('gpxpod', 'GpxPod settings')"
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

import AppNavigationDirectoryItem from './AppNavigationDirectoryItem.vue'

export default {
	name: 'GpxpodNavigation',

	components: {
		AppNavigationDirectoryItem,
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
.addDirItem {
	position: sticky;
	top: 0;
	z-index: 1000;
	border-bottom: 1px solid var(--color-border);
	padding-right: 0 !important;
	::v-deep .app-navigation-entry {
		background-color: var(--color-main-background-blur, var(--color-main-background));
		backdrop-filter: var(--filter-background-blur, none);
		&:hover {
			background-color: var(--color-background-hover);
		}
	}
}

::v-deep .app-navigation-toggle {
	color: var(--color-main-text);
	background-color: var(--color-main-background);
	margin-right: -54px !important;
	&:focus,
	&:hover {
		background-color: var(--color-background-hover) !important;
	}
}

::v-deep .directoryItem {
	padding-right: 0 !important;

	&.openDirectory {
		> a,
		> div {
			background: var(--color-primary-light, lightgrey);
		}

		> a {
			font-weight: bold;
		}
	}

}

::v-deep .trackItem {
	height: 44px;
	padding-right: 0 !important;

	&.selectedTrack .app-navigation-entry {
		background: var(--color-primary-light, lightgrey);

		> a {
			font-weight: bold;
		}
	}
}
</style>
