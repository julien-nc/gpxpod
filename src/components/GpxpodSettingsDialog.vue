<!--
  - @copyright Copyright (c) 2022 Julien Veyssier <julien-nc@posteo.net>
  -
  - @author Julien Veyssier <julien-nc@posteo.net>
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
		<NcAppSettingsDialog
			class="gpxpod-settings-dialog"
			:title="t('gpxpod', 'GpxPod settings')"
			:open.sync="showSettings"
			:show-navigation="true"
			container="#settings-container">
			<NcAppSettingsSection
				id="map"
				:title="t('gpxpod', 'Map settings')"
				class="app-settings-section">
				<div class="app-settings-section__hint">
					{{ t('gpxpod', 'Choose whether the navigation track list shows all tracks or only the ones located in the current map view.') }}
				</div>
				<NcCheckboxRadioSwitch
					:checked="settings.nav_tracks_filter_map_bounds === '1'"
					@update:checked="onCheckboxChanged($event, 'nav_tracks_filter_map_bounds')">
					<FilterIcon :size="20" class="inline-icon" />
					{{ t('gpxpod', 'Filter with map view (dynamic track list)') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:checked="settings.nav_show_hovered_dir_bounds === '1'"
					@update:checked="onCheckboxChanged($event, 'nav_show_hovered_dir_bounds')">
					<RectangleOutlineIcon :size="20" class="inline-icon" />
					{{ t('gpxpod', 'Show directory bounds on hover') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:checked="settings.show_marker_cluster === '1'"
					@update:checked="onCheckboxChanged($event, 'show_marker_cluster')">
					<MapMarkerCircleIcon :size="20" class="inline-icon" />
					{{ t('gpxpod', 'Show track marker cluster') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:checked="settings.show_picture_cluster === '1'"
					@update:checked="onCheckboxChanged($event, 'show_picture_cluster')">
					<ImageIcon :size="20" class="inline-icon" />
					{{ t('gpxpod', 'Show picture marker cluster') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:checked="settings.show_mouse_position_control === '1'"
					@update:checked="onCheckboxChanged($event, 'show_mouse_position_control')">
					<CursorDefaultClickOutlineIcon :size="20" class="inline-icon" />
					{{ t('gpxpod', 'Show mouse position coordinates in the bottom-left map corner') }}
				</NcCheckboxRadioSwitch>
				<div class="oneLine">
					<RulerIcon :size="20" />
					<label for="unit">
						{{ t('gpxpod', 'Distance unit') }}
					</label>
					<select id="unit"
						:value="distanceUnitValue"
						@change="onUnitChange">
						<option value="metric">
							{{ t('gpxpod', 'Metric') }}
						</option>
						<option value="imperial">
							{{ t('gpxpod', 'Imperial (English)') }}
						</option>
						<option value="nautical">
							{{ t('gpxpod', 'Nautical') }}
						</option>
					</select>
				</div>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!isPublicPage"
				id="api-keys"
				:title="t('gpxpod', 'API keys')"
				class="app-settings-section">
				<div class="app-settings-section__hint">
					{{ t('gpxpod', 'If you leave the Maptiler or the Mapbox API key empty, GpxPod will use the ones defined by the Nextcloud admin as defaults.') }}
				</div>
				<div v-if="isAdmin" class="app-settings-section__hint with-icon">
					<AdminIcon :size="24" class="icon" />
					<span v-html="adminApiKeyHint" />
				</div>
				<div class="app-settings-section__hint" v-html="maptilerHint" />
				<div class="oneLine">
					<KeyIcon :size="20" />
					<label for="maptiler-api-key">
						{{ t('gpxpod', 'API key to use Maptiler (mandatory)') }}
					</label>
					<input id="maptiler-api-key"
						ref="maptilerKeyInput"
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
						ref="mapboxKeyInput"
						:value="settings.mapbox_api_key"
						type="text"
						:placeholder="t('gpxpod', 'api key')"
						@input="onMapboxApiKeyChange">
				</div>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="about"
				:title="t('gpxpod', 'About GpxPod')"
				class="app-settings-section">
				<h3 class="app-settings-section__hint">
					{{ '♥ ' + t('gpxpod', 'Thanks for using Gpxpod') + ' ♥ (v' + settings.app_version + ')' }}
				</h3>
				<h3 class="app-settings-section__hint">
					{{ t('gpxpod', 'Bug/issue tracker') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/gpxpod/issues"
					target="_blank"
					class="external">
					https://github.com/julien-nc/gpxpod/issues
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
				<a href="https://github.com/julien-nc/gpxpod/blob/main/docs/user.md"
					target="_blank"
					class="external">
					https://github.com/julien-nc/gpxpod/blob/main/docs/user.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('gpxpod', 'Admin documentation') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/gpxpod/blob/main/docs/admin.md"
					target="_blank"
					class="external">
					https://github.com/julien-nc/gpxpod/blob/main/docs/admin.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('gpxpod', 'Developer documentation') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/gpxpod/blob/main/docs/dev.md"
					target="_blank"
					class="external">
					https://github.com/julien-nc/gpxpod/blob/main/docs/dev.md
					<OpenInNewIcon :size="16" />
				</a>
			</NcAppSettingsSection>
		</NcAppSettingsDialog>
	</div>
</template>

<script>
import RectangleOutlineIcon from 'vue-material-design-icons/RectangleOutline.vue'
import MapMarkerCircleIcon from 'vue-material-design-icons/MapMarkerCircle.vue'
import ImageIcon from 'vue-material-design-icons/Image.vue'
import CursorDefaultClickOutlineIcon from 'vue-material-design-icons/CursorDefaultClickOutline.vue'
import RulerIcon from 'vue-material-design-icons/Ruler.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import {
	// getFilePickerBuilder,
	// showError,
	showSuccess,
} from '@nextcloud/dialogs'
import NcAppSettingsDialog from '@nextcloud/vue/dist/Components/NcAppSettingsDialog.js'
import NcAppSettingsSection from '@nextcloud/vue/dist/Components/NcAppSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import { delay } from '../utils.js'
import AdminIcon from './icons/AdminIcon.vue'

export default {
	name: 'GpxpodSettingsDialog',

	components: {
		AdminIcon,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcCheckboxRadioSwitch,
		KeyIcon,
		OpenInNewIcon,
		RulerIcon,
		FilterIcon,
		RectangleOutlineIcon,
		MapMarkerCircleIcon,
		ImageIcon,
		CursorDefaultClickOutlineIcon,
	},

	inject: ['isPublicPage'],

	props: {
		settings: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			showSettings: false,
			isAdmin: getCurrentUser()?.isAdmin,
			adminSettingsUrl: generateUrl('/settings/admin/additional#gpxpod_prefs'),
		}
	},

	computed: {
		distanceUnitValue() {
			return this.settings.distance_unit ?? 'metric'
		},
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
		adminApiKeyHint() {
			const adminLink = '<a href="' + this.adminSettingsUrl + '" target="blank">' + t('gpxpod', 'GpxPod admin settings') + '</a>'
			return t(
				'gpxpod',
				'As you are an administrator, you can set global API keys in the {adminLink}',
				{ adminLink },
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
				this.saveApiKeys()
			}, 2000)()
		},
		onMapboxApiKeyChange(e) {
			delay(() => {
				this.saveApiKeys()
			}, 2000)()
		},
		saveApiKeys() {
			this.$emit('save-options', {
				maptiler_api_key: this.$refs.maptilerKeyInput.value,
				mapbox_api_key: this.$refs.mapboxKeyInput.value,
			})
			showSuccess(t('gpxpod', 'API keys saved, effective after reloading the page'))
		},
		onCheckboxChanged(newValue, key) {
			this.$emit('save-options', { [key]: newValue ? '1' : '0' })
		},
		onUnitChange(e) {
			this.$emit('save-options', { distance_unit: e.target.value })
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

.inline-icon {
	margin-right: 4px;
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
		&.with-icon {
			display: flex;
			align-items: center;
			.icon {
				margin-right: 8px;
			}
		}
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
		select,
		input {
			flex-grow: 1;
		}
	}
}

::v-deep .gpxpod-settings-dialog .modal-container {
	display: flex !important;
}
</style>
