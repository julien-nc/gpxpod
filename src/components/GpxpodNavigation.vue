<template>
	<AppNavigation ref="nav">
		<template #list>
			<AppNavigationItem
				:title="t('gpxpod', 'Add directories')"
				class="addDirItem"
				:menu-open="addMenuOpen"
				@click="addMenuOpen = true"
				@update:menuOpen="updateAddMenuOpen">
				<template #icon>
					<PlusIcon />
				</template>
				<template #actions>
					<ActionButton
						:close-after-click="true"
						@click="onAddDirectoryClick">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Add one directory') }}
					</ActionButton>
					<ActionButton
						:close-after-click="true"
						@click="onAddDirectoryRecursiveClick">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Recursively add a directory') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
			<AppNavigationDirectoryItem v-for="(dir, dirId) in directories"
				:key="dirId"
				class="directoryItem"
				:directory="dir"
				@open="$emit('open-directory', $event)"
				@close="$emit('close-directory', $event)"
				@remove="$emit('remove-directory', $event)"
				@sort-order-changed="$emit('directory-sort-order-changed', { dirId, sortOrder: $event })"
				@directory-details-click="$emit('directory-details-click', $event)"
				@directory-share-click="$emit('directory-share-click', $event)"
				@track-clicked="$emit('track-clicked', $event)"
				@track-color-changed="$emit('track-color-changed', $event)"
				@track-criteria-changed="$emit('track-criteria-changed', $event)"
				@track-details-click="$emit('track-details-click', $event)"
				@track-share-click="$emit('track-share-click', $event)"
				@track-hover-in="$emit('track-hover-in', $event)"
				@track-hover-out="$emit('track-hover-out', $event)" />
		</template>
		<!--template #footer></template-->
		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<!--button class="settings-button" @click="showSettings">
						{{ t('cospend', 'Cospend settings') }}
					</button-->
					<AppNavigationItem
						:title="t('gpxpod', 'Gpxpod settings')"
						@click="showSettings">
						<template #icon>
							<CogIcon
								class="icon"
								:size="20" />
						</template>
					</AppNavigationItem>
				</div>
			</div>
		</template>
	</AppNavigation>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { dirname } from '@nextcloud/paths'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem.js'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation.js'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton.js'
import AppNavigationDirectoryItem from './AppNavigationDirectoryItem.vue'

export default {
	name: 'GpxpodNavigation',

	components: {
		AppNavigationDirectoryItem,
		AppNavigationItem,
		AppNavigation,
		ActionButton,
		PlusIcon,
		CogIcon,
	},

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
					this.$emit('add-directory', path)
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
					this.$emit('add-directory-recursive', path)
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
	background-color: var(--color-main-background);
	&:hover {
		background-color: var(--color-background-hover);
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

	&.selectedTrack {
		&,
		> a,
		> div {
			background: var(--color-primary-light, lightgrey);
		}

		> a {
			font-weight: bold;
		}
	}
}
</style>
