<template>
	<AppNavigation>
		<template #list>
			<AppNavigationItem
				:title="t('gpxpod', 'Add a directory')"
				class="addDirItem"
				@click="onAddDirectoryClick">
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
						{{ t('gpxpod', 'Add a directory') }}
					</ActionButton>
					<ActionButton
						:close-after-click="true"
						@click="onAddDirectoryRecursiveClick">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Recursive add') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
			<!--BoardNavigationItem v-for="board in boards"
				:key="board.id"
				class="boardItem"
				:board="board"
				:selected="board.id === selectedBoardId"
				@board-clicked="onBoardClicked"
				@delete-board="onBoardDeleted" /-->
		</template>
		<!--template #footer></template-->
	</AppNavigation>
</template>

<script>
import { dirname } from '@nextcloud/paths'
import PlusIcon from 'vue-material-design-icons/Plus'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
// import BoardNavigationItem from './BoardNavigationItem'

export default {
	name: 'GpxpodNavigation',

	components: {
		// BoardNavigationItem,
		AppNavigationItem,
		AppNavigation,
		ActionButton,
		PlusIcon,
	},

	props: {
		directories: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			lastBrowsePath: null,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
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
}

:deep(.boardItem) {
	padding-right: 0 !important;
	&.selectedBoard {
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
