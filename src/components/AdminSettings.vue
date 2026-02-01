<template>
	<div id="gpxpod_prefs" class="section">
		<h2>
			<GpxpodIcon />
			<span>Gpxpod</span>
		</h2>
		<div class="gpxpod-content">
			<div>
				<NcNoteCard type="info">
					<span v-html="mainHintHtml" />
				</NcNoteCard>
				<NcNoteCard type="info">
					{{ t('gpxpod', 'The API keys defined here will be used by all users. Each user can set personal API keys to use instead of those ones.') }}
				</NcNoteCard>
			</div>
			<NcTextField
				v-model="state.maptiler_api_key"
				:label="t('gpxpod', 'Maptiler API key')"
				type="password"
				:placeholder="t('gpxpod', 'my-api-key')"
				:show-trailing-button="!!state.maptiler_api_key"
				@update:model-value="onSensitiveInput"
				@trailing-button-click="state.maptiler_api_key = ''; onSensitiveInput()">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
			</NcTextField>
			<NcNoteCard type="info">
				{{ t('gpxpod', 'GpxPod uses Nominatim by default. As an alternative, you can use Photon by setting the following setting to the API URL of a Photon instance.') }}
			</NcNoteCard>
			<NcTextField
				v-model="state.geocoder_url"
				:label="t('gpxpod', 'Photon geocoder API URL')"
				:placeholder="t('gpxpod', 'For example: {example}', { example: 'https://photon.komoot.io/api/' })"
				:show-trailing-button="!!state.geocoder_url"
				@update:model-value="onInput"
				@trailing-button-click="state.geocoder_url = ''; onInput()">
				<template #icon>
					<SearchWebIcon :size="20" />
				</template>
			</NcTextField>
			<NcFormBox>
				<NcFormBoxSwitch :model-value="state.proxy_osm"
					:label="t('gpxpod', 'Proxy map tiles/vectors requests via Nextcloud')"
					@update:model-value="onCheckboxChanged($event, 'proxy_osm')" />
				<NcFormBoxSwitch :model-value="state.use_gpsbabel"
					:label="t('gpxpod', 'Use GpsBabel to convert files (instead of native converters)')"
					@update:model-value="onCheckboxChanged($event, 'use_gpsbabel')" />
			</NcFormBox>
			<TileServerList
				class="admin-tile-server-list"
				:tile-servers="state.extra_tile_servers"
				:is-admin="true" />
		</div>
	</div>
</template>

<script>
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'
import SearchWebIcon from 'vue-material-design-icons/SearchWeb.vue'

import GpxpodIcon from './icons/GpxpodIcon.vue'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcTextField from '@nextcloud/vue/components/NcTextField'

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
		KeyOutlineIcon,
		SearchWebIcon,
		NcNoteCard,
		NcFormBox,
		NcFormBoxSwitch,
		NcTextField,
	},

	props: [],

	data() {
		return {
			state: loadState('gpxpod', 'admin-config'),
			mainHintHtml: t('gpxpod', 'You can create an API key on {maptilerLink}',
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
		subscribe('tile-server-edited', this.onTileServerEdited)
	},

	unmounted() {
		unsubscribe('tile-server-deleted', this.onTileServerDeleted)
		unsubscribe('tile-server-added', this.onTileServerAdded)
		unsubscribe('tile-server-edited', this.onTileServerEdited)
	},

	methods: {
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' }, false)
		},
		onInput() {
			delay(() => {
				this.saveOptions({
					geocoder_url: this.state.geocoder_url,
				}, false)
			}, 2000)()
		},
		onSensitiveInput() {
			delay(() => {
				if (this.state.maptiler_api_key === 'dummyApiKey') {
					return
				}
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
					showError(t('gpxpod', 'Failed to delete the tile server'))
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
					showError(t('gpxpod', 'Failed to add the tile server'))
					console.debug(error)
				})
		},
		onTileServerEdited({ ts, isAdminTileServer }) {
			console.debug('tile server edited', isAdminTileServer, ts)
			const { id: _, ...values } = ts
			const req = {
				...values,
			}
			const url = generateUrl('/apps/gpxpod/admin/tileservers/{id}', { id: ts.id })
			axios.put(url, req)
				.then((response) => {
					// TODO update item in state.extra_tile_servers
					const index = this.state.extra_tile_servers.findIndex(item => item.id === ts.id)
					if (index !== -1) {
						Object.assign(this.state.extra_tile_servers[index], values)
					}
				}).catch((error) => {
					showError(t('gpxpod', 'Failed to update the tile server'))
					console.debug(error)
				})
		},
	},
}
</script>

<style scoped lang="scss">
#gpxpod_prefs {
	h2 {
		display: flex;
		align-items: center;
		justify-content: start;
		gap: 12px;
	}

	.gpxpod-content {
		max-width: 800px;
		display: flex;
		flex-direction: column;
		gap: 8px;
		margin-left: 30px;
	}

	.subsection-title {
		font-weight: bold;
	}

	.admin-tile-server-list {
		margin-top: 12px;
	}
}
</style>
