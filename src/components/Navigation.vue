<template>
	<NcAppNavigation ref="nav"
		class="gpxpodNavigation"
		:class="{ compact }"
		:style="cssVars">
		<template v-if="!isPublicPage" #search>
			<NcAppNavigationSearch v-model="directoryFilterQuery"
				label="plop"
				:placeholder="t('gpxpod', 'Search directories')">
				<template #actions>
					<NcActions>
						<template #icon>
							<FolderPlusIcon />
						</template>
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
					</NcActions>
				</template>
			</NcAppNavigationSearch>
		</template>
		<template #list>
			<NavigationDirectoryItem v-for="dir in filteredDirectories"
				:key="dir.id"
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
import FolderPlusIcon from 'vue-material-design-icons/FolderPlus.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'

import NavigationDirectoryItem from './NavigationDirectoryItem.vue'

import {
	getFilePickerBuilder,
	// FilePickerType,
} from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { dirname, basename } from '@nextcloud/paths'

export default {
	name: 'Navigation',

	components: {
		NavigationDirectoryItem,
		NcAppNavigationItem,
		NcAppNavigation,
		NcActionButton,
		NcAppNavigationSearch,
		NcActions,
		PlusIcon,
		CogIcon,
		FolderPlusIcon,
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
			directoryFilterQuery: '',
		}
	},

	computed: {
		cssVars() {
			return {
				'--font-size': this.fontScale + '%',
			}
		},
		directoryList() {
			return Object.values(this.directories)
		},
		filteredDirectories() {
			return this.directoryFilterQuery
				? this.directoryList.filter(d => basename(d.path).toLowerCase().includes(this.directoryFilterQuery.toLowerCase()))
				: this.directoryList
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
		async onAddDirectoryClick() {
			const picker = getFilePickerBuilder(t('gpxpod', 'Add directory'))
				.setMultiSelect(false)
				// .setType(FilePickerType.Choose)
				.addButton({
					label: t('gpxpod', 'Pick current directory'),
					variant: 'primary',
					callback: (nodes) => {
						if (nodes.length === 0) {
							return
						}
						const root = nodes[0].root
						const filename = nodes[0].attributes.filename
						const path = filename.replace(root, '')
						emit('directory-add', path)
						this.lastBrowsePath = dirname(path)
					},
				})
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.startAt(this.lastBrowsePath)
				.build()
			picker.pick()
		},
		onAddDirectoryRecursiveClick() {
			const picker = getFilePickerBuilder(t('gpxpod', 'Recursively add a directory'))
				.setMultiSelect(false)
				// .setType(FilePickerType.Choose)
				.addButton({
					label: t('gpxpod', 'Pick current directory'),
					variant: 'primary',
					callback: (nodes) => {
						if (nodes.length === 0) {
							return
						}
						const root = nodes[0].root
						const filename = nodes[0].attributes.filename
						const path = filename.replace(root, '')
						emit('directory-add-recursive', path)
						this.lastBrowsePath = path
					},
				})
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.startAt(this.lastBrowsePath)
				.build()
			picker.pick()
		},
	},
}
</script>

<style scoped lang="scss">
.gpxpodNavigation {
	font-size: var(--font-size) !important;
}
</style>
