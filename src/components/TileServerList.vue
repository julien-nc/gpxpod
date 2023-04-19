<template>
	<div class="tile-server-list">
		<TileServerItem v-for="ts in tileServers"
			:key="ts.id"
			class="tile-server-list-item"
			:tile-server="ts"
			:show-delete-button="!readOnly && (isAdmin || ts.user_id !== null)"
			@delete="onTileServerDelete(ts)" />
		<NcButton v-if="!readOnly"
			@click="showAddModal = true">
			<template #icon>
				<PlusIcon />
			</template>
			{{ t('gpxpod', 'Add tile server') }}
		</NcButton>
		<NcModal v-if="showAddModal"
			size="normal"
			@close="showAddModal = false">
			<div class="modal-content">
				<TileServerAddForm
					:is-admin="isAdmin"
					@submit="onTileServerAdded" />
			</div>
		</NcModal>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import TileServerAddForm from './TileServerAddForm.vue'
import TileServerItem from './TileServerItem.vue'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'TileServerList',

	components: {
		TileServerAddForm,
		TileServerItem,
		NcButton,
		NcModal,
		PlusIcon,
	},

	props: {
		tileServers: {
			type: Array,
			required: true,
		},
		isAdmin: {
			type: Boolean,
			default: false,
		},
		readOnly: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			showAddModal: false,
		}
	},

	computed: {
	},

	beforeMount() {
	},

	beforeDestroy() {
	},

	methods: {
		onTileServerDelete(ts) {
			// TODO delete
			emit('tile-server-deleted', ts.id)
		},
		onTileServerAdded(ts) {
			emit('tile-server-added', ts)
			this.showAddModal = false
		},
	},
}
</script>

<style scoped lang="scss">
.tile-server-list {
	.tile-server-list-item {
		margin-bottom: 8px;
	}
}

.modal-content {
	padding: 12px;
}
</style>
