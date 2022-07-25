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
			<AppNavigationDirectoryItem v-for="(dir, path) in directories"
				:key="path"
				class="directoryItem"
				:directory="dir"
				:path="path"
				@open="$emit('open-directory', $event)"
				@close="$emit('close-directory', $event)"
				@track-clicked="$emit('track-clicked', $event)"
				@track-color-changed="$emit('track-color-changed', $event)"
				@track-hover-in="$emit('track-hover-in', $event)"
				@track-hover-out="$emit('track-hover-out', $event)" />
		</template>
		<!--template #footer></template-->
	</AppNavigation>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { dirname } from '@nextcloud/paths'
import PlusIcon from 'vue-material-design-icons/Plus'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationDirectoryItem from './AppNavigationDirectoryItem'

export default {
	name: 'GpxpodNavigation',

	components: {
		AppNavigationDirectoryItem,
		AppNavigationItem,
		AppNavigation,
		ActionButton,
		PlusIcon,
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
		updateAddMenuOpen(isOpen) {
			if (!isOpen) {
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
	border-bottom: 1px solid var(--color-border);
	padding-right: 0 !important;
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
