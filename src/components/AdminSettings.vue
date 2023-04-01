<template>
	<div id="gpxpod_prefs" class="section">
		<h2>
			<GpxpodIcon class="gpxpod-icon" />
			<span>Gpxpod</span>
		</h2>
		<p class="settings-hint">
			<InformationVariant :size="24" class="icon" />
			<span v-html="mainHintHtml" />
		</p>
		<p class="settings-hint">
			<InformationVariant :size="24" class="icon" />
			{{ t('gpxpod', 'The API keys defined here will be used by all users. Each user can set personal API keys to use intead of those ones.') }}
		</p>
		<div class="field">
			<label for="gpxpod-maptiler-apikey">
				<Key :size="20" class="icon" />
				{{ t('gpxpod', 'Maptiler API key') }}
			</label>
			<input id="gpxpod-maptiler-apikey"
				v-model="state.maptiler_api_key"
				type="text"
				:placeholder="t('gpxpod', 'api key')"
				@input="onInput">
		</div>
		<div class="field">
			<label for="gpxpod-mapbox-apikey">
				<Key :size="20" class="icon" />
				{{ t('gpxpod', 'Mapbox API key (aka Token)') }}
			</label>
			<input id="gpxpod-mapbox-apikey"
				v-model="state.mapbox_api_key"
				type="text"
				:placeholder="t('gpxpod', 'api key')"
				@input="onInput">
		</div>
		<div class="field">
			<NcCheckboxRadioSwitch
				:checked="state.use_gpsbabel"
				@update:checked="onCheckboxChanged($event, 'use_gpsbabel')">
				{{ t('gpxpod', 'Use GpsBabel to convert files (instead of native converters)') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

const NcCheckboxRadioSwitch = () => import('@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js')

const InformationVariant = () => import('vue-material-design-icons/InformationVariant.vue')
const Key = () => import('vue-material-design-icons/Key.vue')
const GpxpodIcon = () => import('./icons/GpxpodIcon.vue')

export default {
	name: 'AdminSettings',

	components: {
		GpxpodIcon,
		InformationVariant,
		Key,
		NcCheckboxRadioSwitch,
	},

	props: [],

	data() {
		return {
			state: loadState('gpxpod', 'admin-config'),
			mainHintHtml: t('gpxpod', 'Those default keys are very limited. Please consider creating your own API keys on {maptilerLink} and {mapboxLink}',
				{
					maptilerLink: '<a href="https://maptiler.com" class="external" target="blank">https://maptiler.com</a>',
					mapboxLink: '<a href="https://mapbox.com" class="external" target="blank">https://mapbox.com</a>',
				},
				null, { escape: false, sanitize: false }),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		onInput() {
			delay(() => {
				this.saveOptions({
					maptiler_api_key: this.state.maptiler_api_key,
					mapbox_api_key: this.state.mapbox_api_key,
				})
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/gpxpod/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('gpxpod', 'GpxPod admin options saved'))
			}).catch((error) => {
				showError(
					t('gpxpod', 'Failed to save GpxPod admin options')
					+ ': ' + (error.response?.request?.responseText ?? '')
				)
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
	}

	h2 {
		display: flex;
		.gpxpod-icon {
			margin-right: 12px;
		}
	}
}
</style>
