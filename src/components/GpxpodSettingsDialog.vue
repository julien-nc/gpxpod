<!--
  - @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author Julien Veyssier <eneiluj@posteo.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div id="settings-container">
		<AppSettingsDialog
			class="gpxpod-settings-dialog"
			:open.sync="showSettings"
			:show-navigation="true"
			container="#settings-container">
			<AppSettingsSection v-if="!pageIsPublic"
				:title="t('gpxpod', 'API keys')"
				class="app-settings-section">
				<div class="app-settings-section__hint">
					{{ t('gpxpod', 'If you leave the Maptiler or the Mapbox API key empty, Gpxpod will use the ones defined by the Nextcloud admin as defaults.') }}
				</div>
				<div class="app-settings-section__hint" v-html="maptilerHint" />
				<div class="oneLine">
					<KeyIcon :size="20" />
					<label for="maptiler-api-key">
						{{ t('gpxpod', 'API key to use Maptiler (mandatory)') }}
					</label>
					<input id="maptiler-api-key"
						:value="settings.maptiler_api_key"
						type="text"
						:placeholder="t('gpxpod', 'api key')"
						@input="onMaptilerApiKeyChange">
				</div>
				<div class="app-settings-section__hint" v-html="mapboxHint" />
				<div class="oneLine">
					<KeyIcon :size="20" />
					<label for="mapbox-api-key">
						{{ t('gpxpod', 'API key to use Mapbox (to search for locations)') }}
					</label>
					<input id="mapbox-api-key"
						:value="settings.mapbox_api_key"
						type="text"
						:placeholder="t('gpxpod', 'api key')"
						@input="onMapboxApiKeyChange">
				</div>
			</AppSettingsSection>
			<AppSettingsSection v-if="!pageIsPublic"
				:title="t('gpxpod', 'Map settings')"
				class="app-settings-section">
				<div class="app-settings-section__hint">
					{{ t('gpxpod', 'Choose whether the track list in the left side shows all track or only the ones intersecting the current map bounds.') }}
				</div>
				<CheckboxRadioSwitch
					:checked="settings.nav_tracks_filter_map_bounds === '1'"
					@update:checked="onCheckboxChanged($event, 'nav_tracks_filter_map_bounds')">
					{{ t('gpxpod', 'Filter with map bounds (dynamic track list)') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch
					:checked="settings.show_mouse_position_control === '1'"
					@update:checked="onCheckboxChanged($event, 'show_mouse_position_control')">
					{{ t('gpxpod', 'Show mouse position coordinates in the bottom-left map corner') }}
				</CheckboxRadioSwitch>
			</AppSettingsSection>
			<AppSettingsSection
				:title="t('gpxpod', 'About Gpxpod')"
				class="app-settings-section">
				<h3 class="app-settings-section__hint">
					{{ t('gpxpod', 'Thanks for using Gpxpod') + ' ♥' }}
				</h3>
				<h3 class="app-settings-section__hint">
					{{ t('gpxpod', 'Bug/issue tracker') + ': ' }}
				</h3>
				<a href="https://github.com/eneiluj/gpxpod-nc/issues"
					target="_blank"
					class="external">
					https://github.com/eneiluj/gpxpod-nc/issues
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('gpxpod', 'Translation') + ': ' }}
				</h3>
				<a href="https://crowdin.com/project/gpxpod"
					target="_blank"
					class="external">
					https://crowdin.com/project/gpxpod
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('gpxpod', 'User documentation') + ': ' }}
				</h3>
				<a href="https://github.com/eneiluj/gpxpod-nc/blob/master/docs/user.md"
					target="_blank"
					class="external">
					https://github.com/eneiluj/gpxpod-nc/blob/master/docs/user.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('gpxpod', 'Admin documentation') + ': ' }}
				</h3>
				<a href="https://github.com/eneiluj/gpxpod-nc/blob/master/docs/admin.md"
					target="_blank"
					class="external">
					https://github.com/eneiluj/gpxpod-nc/blob/master/docs/admin.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('gpxpod', 'Developer documentation') + ': ' }}
				</h3>
				<a href="https://github.com/eneiluj/gpxpod-nc/blob/master/docs/dev.md"
					target="_blank"
					class="external">
					https://github.com/eneiluj/gpxpod-nc/blob/master/docs/dev.md
					<OpenInNewIcon :size="16" />
				</a>
			</AppSettingsSection>
		</AppSettingsDialog>
	</div>
</template>

<script>
import KeyIcon from 'vue-material-design-icons/Key'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
// import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import { delay } from '../utils'

export default {
	name: 'GpxpodSettingsDialog',

	components: {
		AppSettingsDialog,
		AppSettingsSection,
		CheckboxRadioSwitch,
		KeyIcon,
		OpenInNewIcon,
	},

	props: {
		settings: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			showSettings: false,
			pageIsPublic: false,
		}
	},

	computed: {
		maptilerHint() {
			const maptilerLink = '<a href="https://maptiler.com" target="blank">https://maptiler.com</a>'
			return t(
				'gpxpod',
				'If your admin hasn\'t defined an API key, you can get one for free on {maptilerLink}. Create an account then go to "Account" -> "API keys" and create a key or use your default one.',
				{ maptilerLink },
				null,
				{ escape: false, sanitize: false },
			)
		},
		mapboxHint() {
			const mapboxLink = '<a href="https://mapbox.com" target="blank">https://mapbox.com</a>'
			return t(
				'gpxpod',
				'You can also create a Mapbox API key for free on {mapboxLink}. Create an account then visit the "Tokens" section. Create a token or use your default one. A token is an API key.',
				{ mapboxLink },
				null,
				{ escape: false, sanitize: false },
			)
		},
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
	},

	beforeDestroy() {
		unsubscribe('show-settings', this.handleShowSettings)
	},

	methods: {
		handleShowSettings() {
			this.showSettings = true
		},
		onMaptilerApiKeyChange(e) {
			delay(() => {
				this.$emit('save-options', { maptiler_api_key: e.target.value })
			}, 2000)()
		},
		onMapboxApiKeyChange(e) {
			delay(() => {
				this.$emit('save-options', { mapbox_api_key: e.target.value })
			}, 2000)()
		},
		onCheckboxChanged(newValue, key) {
			this.$emit('save-options', { [key]: newValue ? '1' : '0' })
		},
	},
}
</script>

<style lang="scss" scoped>
a.external {
	display: flex;
	align-items: center;
	> * {
		margin: 0 2px 0 2px;
	}
}

.app-settings-section {
	margin-bottom: 80px;
	&.last {
		margin-bottom: 0;
	}
	&__title {
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
	&__hint {
		color: var(--color-text-lighter);
		padding: 8px 0;
	}
	&__input {
		width: 100%;
	}

	.shortcut-description {
		width: calc(100% - 160px);
	}

	.oneLine {
		display: flex;
		align-items: center;
		> * {
			margin: 0 4px 0 4px;
		}
		label {
			width: 300px;
		}
		input {
			flex-grow: 1;
		}
	}
}

::v-deep .gpxpod-settings-dialog .modal-container {
	display: flex !important;
}
</style>
