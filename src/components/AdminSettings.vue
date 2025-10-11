<template>
	<div id="gpxpod_prefs" class="section">
		<h2>
			<GpxpodIcon class="gpxpod-icon" />
			<span>Gpxpod</span>
		</h2>
		<p class="settings-hint">
			<InformationOutlineIcon :size="24" class="icon" />
			<span v-html="mainHintHtml" />
		</p>
		<p class="settings-hint">
			<InformationOutlineIcon :size="24" class="icon" />
			{{ t('gpxpod', 'The API keys defined here will be used by all users. Each user can set personal API keys to use intead of those ones.') }}
		</p>
		<div class="field">
			<label for="gpxpod-maptiler-apikey">
				<KeyIcon :size="20" class="icon" />
				{{ t('gpxpod', 'Maptiler API key') }}
			</label>
			<input id="gpxpod-maptiler-apikey"
				v-model="state.maptiler_api_key"
				type="password"
				:placeholder="t('gpxpod', 'api key')"
				@input="onInput">
		</div>
		<div class="field">
			<NcCheckboxRadioSwitch
				:model-value="state.proxy_osm"
				@update:model-value="onCheckboxChanged($event, 'proxy_osm')">
				{{ t('gpxpod', 'Proxy map tiles/vectors requests via Nextcloud') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div class="field">
			<NcCheckboxRadioSwitch
				:model-value="state.use_gpsbabel"
				@update:model-value="onCheckboxChanged($event, 'use_gpsbabel')">
				{{ t('gpxpod', 'Use GpsBabel to convert files (instead of native converters)') }}
			</NcCheckboxRadioSwitch>
		</div>
		<TileServerList
			class="admin-tile-server-list"
			:tile-servers="state.extra_tile_servers"
			:is-admin="true" />
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'

import GpxpodIcon from './icons/GpxpodIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import TileServerList from './TileServerList.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
		TileServerList,
		GpxpodIcon,
		InformationOutlineIcon,
		KeyIcon,
		NcCheckboxRadioSwitch,
	},

	props: [],

	data() {
		return {
			state: loadState('gpxpod', 'admin-config'),
			mainHintHtml: t('gpxpod', 'The default key is very limited. Please consider creating your own API key on {maptilerLink}',
				{
					maptilerLink: '<a href="https://maptiler.com" class="external" target="blank">https://maptiler.com</a>',
				},
				null, { escape: false, sanitize: false }),
		}
	},

	watch: {
	},

	mounted() {
		subscribe('tile-server-deleted', this.onTileServerDeleted)
		subscribe('tile-server-added', this.onTileServerAdded)
	},

	unmounted() {
		unsubscribe('tile-server-deleted', this.onTileServerDeleted)
		unsubscribe('tile-server-added', this.onTileServerAdded)
	},

	methods: {
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' }, false)
		},
		onInput() {
			delay(() => {
				this.saveOptions({
					maptiler_api_key: this.state.maptiler_api_key,
				}, true)
			}, 2000)()
		},
		async saveOptions(values, sensitive = true) {
			if (sensitive) {
				await confirmPassword()
			}

			const req = {
				values,
			}
			const url = sensitive
				? generateUrl('/apps/gpxpod/admin-config/sensitive')
				: generateUrl('/apps/gpxpod/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('gpxpod', 'GpxPod admin options saved'))
			}).catch((error) => {
				showError(t('gpxpod', 'Failed to save GpxPod admin options'))
				console.debug(error)
			})
		},
		onTileServerDeleted(id) {
			const url = generateUrl('/apps/gpxpod/admin/tileservers/{id}', { id })
			axios.delete(url)
				.then((response) => {
					const index = this.state.extra_tile_servers.findIndex(ts => ts.id === id)
					if (index !== -1) {
						this.state.extra_tile_servers.splice(index, 1)
					}
				}).catch((error) => {
					showError(t('gpxpod', 'Failed to delete tile server'))
					console.debug(error)
				})
		},
		onTileServerAdded(ts) {
			const req = {
				...ts,
			}
			const url = generateUrl('/apps/gpxpod/admin/tileservers')
			axios.post(url, req)
				.then((response) => {
					this.state.extra_tile_servers.push(response.data)
				}).catch((error) => {
					showError(t('gpxpod', 'Failed to add tile server'))
					console.debug(error)
				})
		},
	},
}
</script>

<style scoped lang="scss">
#gpxpod_prefs {
	.field {
		display: flex;
		align-items: center;
		margin-left: 30px;

		input,
		label {
			width: 300px;
		}

		label {
			display: flex;
			align-items: center;
		}
		.icon {
			margin-right: 8px;
		}
	}

	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 8px;
		}
	}

	h2 {
		display: flex;
		.gpxpod-icon {
			margin-right: 12px;
		}
	}

	.subsection-title {
		font-weight: bold;
	}

	.admin-tile-server-list {
		margin-top: 12px;
	}
}
</style>
