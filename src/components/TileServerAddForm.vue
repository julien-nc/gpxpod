<template>
	<div class="tile-server-add-form">
		<h2>
			{{ formTitle }}
		</h2>
		<div class="field">
			<label for="type-select">
				{{ t('gpxpod', 'Type') }}
			</label>
			<select
				id="type-select"
				v-model="type">
				<option :value="TS_VECTOR">
					{{ t('gpxpod', 'Vector') }}
				</option>
				<option :value="TS_RASTER">
					{{ t('gpxpod', 'Raster') }}
				</option>
			</select>
		</div>
		<NcTextField
			:value.sync="name"
			:label="t('gpxpod', 'Name')"
			:label-visible="true"
			:placeholder="t('gpxpod', 'My tile server')"
			:show-trailing-button="!!name"
			@keydown.enter="onSubmit"
			@trailing-button-click="name = ''" />
		<NcTextField
			:value.sync="url"
			:label="t('gpxpod', 'Server address')"
			:label-visible="true"
			placeholder="https://..."
			:show-trailing-button="!!url"
			@keydown.enter="onSubmit"
			@trailing-button-click="url = ''" />
		<p v-if="type === TS_RASTER" class="settings-hint">
			<InformationOutline :size="24" class="icon" />
			{{ t('gpxpod', 'A raster tile server address must contain "{x}", "{y}" and "{z}" and can optionally contain "{s}". For example {exampleUrl}', { exampleUrl: 'https://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png' }) }}
		</p>
		<p v-else-if="type === TS_VECTOR" class="settings-hint">
			<InformationOutline :size="24" class="icon" />
			{{ t('gpxpod', 'A vector tile server address can point to a MapTiler style.json file, for example {exampleUrl}. It can contain GET parameters like the API key.', { exampleUrl: 'https://api.maptiler.com/maps/hybrid/style.json?key=xxxxxxxxxxxxxxxxxx' }) }}
		</p>
		<NcInputField v-if="type === TS_RASTER"
			:value.sync="minZoom"
			type="number"
			min="1"
			max="24"
			step="1"
			:label="t('gpxpod', 'Min zoom')"
			:label-visible="true"
			placeholder="1..24"
			:show-trailing-button="!!minZoom"
			@keydown.enter="onSubmit"
			@trailing-button-click="minZoom = ''">
			<!-- at the moment, there is no default icon when type is number -->
			<template #trailing-button-icon>
				<CloseIcon :size="20" />
			</template>
		</NcInputField>
		<NcInputField v-if="type === TS_RASTER"
			:value.sync="maxZoom"
			type="number"
			min="1"
			max="24"
			step="1"
			:label="t('gpxpod', 'Max zoom')"
			:label-visible="true"
			placeholder="1..24"
			:show-trailing-button="!!maxZoom"
			@keydown.enter="onSubmit"
			@trailing-button-click="maxZoom = ''">
			<template #trailing-button-icon>
				<CloseIcon :size="20" />
			</template>
		</NcInputField>
		<NcTextField v-if="type === TS_RASTER"
			:value.sync="attribution"
			:label="t('gpxpod', 'Attribution')"
			:label-visible="true"
			:placeholder="t('gpxpod', 'Map data from...')"
			:show-trailing-button="!!attribution"
			@keydown.enter="onSubmit"
			@trailing-button-click="attribution = ''" />
		<div class="footer">
			<NcButton
				:disabled="!valid"
				@click="onSubmit">
				{{ t('gpxpod', 'Create') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'

import { TS_RASTER, TS_VECTOR } from '../tileServers.js'

const InformationOutline = () => import('vue-material-design-icons/InformationOutline.vue')

export default {
	name: 'TileServerAddForm',

	components: {
		NcButton,
		NcTextField,
		NcInputField,
		CloseIcon,
		InformationOutline,
	},

	props: {
		isAdmin: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			TS_VECTOR,
			TS_RASTER,
			type: TS_VECTOR,
			name: '',
			url: '',
			attribution: '',
			minZoom: '1',
			maxZoom: '19',
		}
	},

	computed: {
		valid() {
			return !!this.name && !!this.url
				&& (
					this.type === TS_VECTOR
					|| (!!this.attribution && !!this.minZoom && !!this.maxZoom)
				)
		},
		formTitle() {
			return this.isAdmin
				? t('gpxpod', 'Add a global tile server')
				: t('gpxpod', 'Add a personal tile server')
		},
	},

	beforeMount() {
	},

	beforeDestroy() {
	},

	methods: {
		onSubmit() {
			const common = {
				name: this.name,
				url: this.url,
				type: this.type,
			}
			const ts = this.type === TS_VECTOR
				? common
				: {
					...common,
					min_zoom: parseInt(this.minZoom),
					max_zoom: parseInt(this.maxZoom),
					attribution: this.attribution,
				}
			this.$emit('submit', ts)
		},
	},
}
</script>

<style scoped lang="scss">
.tile-server-add-form {
	h2 {
		text-align: center;
	}
	.field {
		display: flex;
		flex-direction: column;
	}
	.footer {
		margin-top: 12px;
		display: flex;
		justify-content: end;
	}
	.settings-hint {
		margin: 8px 0;
		display: flex;
		align-items: center;
		.icon {
			margin-right: 8px;
		}
	}
}
</style>
