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
			v-model:open="showSettings"
			:name="t('gpxpod', 'GpxPod settings')"
			:title="t('gpxpod', 'GpxPod settings')"
			:show-navigation="true"
			class="gpxpod-settings-dialog"
			container="#settings-container">
			<NcAppSettingsSection
				id="map"
				:name="t('gpxpod', 'Map')"
				:title="t('gpxpod', 'Map')"
				class="app-settings-section">
				<div class="app-settings-section__hint">
					{{ t('gpxpod', 'Choose whether the navigation track list shows all tracks or only the ones located in the current map view.') }}
				</div>
				<NcCheckboxRadioSwitch
					:model-value="settings.nav_tracks_filter_map_bounds === '1'"
					@update:model-value="onCheckboxChanged($event, 'nav_tracks_filter_map_bounds')">
					<div class="checkbox-inner">
						<FilterIcon :size="20" class="inline-icon" />
						{{ t('gpxpod', 'Filter with map view (dynamic track list)') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.nav_show_hovered_dir_bounds === '1'"
					@update:model-value="onCheckboxChanged($event, 'nav_show_hovered_dir_bounds')">
					<div class="checkbox-inner">
						<RectangleOutlineIcon :size="20" class="inline-icon" />
						{{ t('gpxpod', 'Show directory bounds on hover') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.global_track_colorization === '1'"
					@update:model-value="onCheckboxChanged($event, 'global_track_colorization')">
					<div class="checkbox-inner">
						<PaletteIcon :size="20" class="inline-icon" />
						{{ t('gpxpod', 'Use all the segments in a track to define the color gradient (instead of having independent segments)') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.show_marker_cluster === '1'"
					@update:model-value="onCheckboxChanged($event, 'show_marker_cluster')">
					<div class="checkbox-inner">
						<MapMarkerCircleIcon :size="20" class="inline-icon" />
						{{ t('gpxpod', 'Show track marker cluster') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.show_picture_cluster === '1'"
					@update:model-value="onCheckboxChanged($event, 'show_picture_cluster')">
					<div class="checkbox-inner">
						<ImageIcon :size="20" class="inline-icon" />
						{{ t('gpxpod', 'Show picture marker cluster') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.show_mouse_position_control === '1'"
					@update:model-value="onCheckboxChanged($event, 'show_mouse_position_control')">
					<div class="checkbox-inner">
						<CursorDefaultClickOutlineIcon :size="20" class="inline-icon" />
						{{ t('gpxpod', 'Show mouse position coordinates in the bottom-left map corner') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.compact_mode === '1'"
					@update:model-value="onCheckboxChanged($event, 'compact_mode')">
					<div class="checkbox-inner">
						<ViewCompactOutlineIcon :size="20" class="inline-icon" />
						{{ t('gpxpod', 'Compact navigation view') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.line_border === '1'"
					@update:model-value="onCheckboxChanged($event, 'line_border')">
					<div class="checkbox-inner">
						<MinusIcon :size="20" class="inline-icon" />
						{{ t('gpxpod', 'Draw line borders') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.direction_arrows === '1'"
					@update:model-value="onCheckboxChanged($event, 'direction_arrows')">
					<div class="checkbox-inner">
						<ArrowRightIcon :size="20" class="inline-icon" />
						{{ t('gpxpod', 'Draw line direction arrows') }}
					</div>
				</NcCheckboxRadioSwitch>
				<div class="oneLine">
					<ArrowRightIcon :size="20" />
					<label for="arrows-scale">
						{{ t('gpxpod', 'Arrows scale factor') }}
					</label>
					<input id="arrows-scale"
						type="number"
						:value="settings.arrows_scale_factor"
						min="0.1"
						max="2"
						step="0.1"
						@change="onInputChange($event, 'arrows_scale_factor')">
				</div>
				<div class="oneLine">
					<ArrowRightIcon :size="20" />
					<label for="arrows-spacing">
						{{ t('gpxpod', 'Arrows spacing') }}
					</label>
					<input id="arrows-spacing"
						type="number"
						:value="settings.arrows_spacing"
						min="10"
						max="400"
						step="1"
						@change="onInputChange($event, 'arrows_spacing')">
				</div>
				<div class="oneLine">
					<ArrowSplitVerticalIcon :size="20" />
					<label for="line-width">
						{{ t('gpxpod', 'Track line width') }}
					</label>
					<input id="line-width"
						type="number"
						:value="settings.line_width"
						min="1"
						max="20"
						step="0.5"
						@change="onInputChange($event, 'line_width')">
				</div>
				<div class="oneLine">
					<OpacityIcon :size="20" />
					<label for="line-opacity">
						{{ t('gpxpod', 'Track line opacity') }}
					</label>
					<input id="line-opacity"
						type="number"
						:value="settings.line_opacity"
						min="0"
						max="1"
						step="0.1"
						@change="onInputChange($event, 'line_opacity')">
				</div>
				<div class="oneLine">
					<RulerIcon :size="20" />
					<label for="unit">
						{{ t('gpxpod', 'Distance unit') }}
					</label>
					<select id="unit"
						:value="distanceUnitValue"
						@change="onInputChange($event, 'distance_unit')">
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
				<div class="oneLine">
					<ChartAreasplineVariantIcon :size="20" />
					<label for="exaggeration">
						{{ t('gpxpod', '3D elevation exaggeration (effective after page reload)') }}
					</label>
					<input id="exaggeration"
						type="number"
						:value="settings.terrainExaggeration"
						min="0.1"
						max="10"
						step="0.1"
						@change="onInputChange($event, 'terrainExaggeration')">
				</div>
				<div class="oneLine">
					<FormatSizeIcon :size="20" />
					<label for="fontsize">
						{{ t('gpxpod', 'Font scale factor') }} (%)
					</label>
					<input id="fontsize"
						type="number"
						:value="settings.fontScale"
						min="80"
						max="120"
						step="1"
						@change="onInputChange($event, 'fontScale')">
				</div>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!isPublicPage"
				id="api-keys"
				:name="t('gpxpod', 'API keys')"
				:title="t('gpxpod', 'API keys')"
				class="app-settings-section">
				<div class="app-settings-section__hint">
					{{ t('gpxpod', 'If you leave the Maptiler API key empty, GpxPod will use the one defined by the Nextcloud admin as default.') }}
				</div>
				<div v-if="isAdmin" class="app-settings-section__hint with-icon">
					<AdminIcon :size="24" class="icon" />
					<span v-html="adminApiKeyHint" />
				</div>
				<div class="app-settings-section__hint" v-html="maptilerHint" />
				<NcTextField
					:model-value="settings.maptiler_api_key"
					:label="t('gpxpod', 'API key to use Maptiler (mandatory)')"
					type="password"
					:placeholder="t('gpxpod', 'my-api-key')"
					:show-trailing-button="!!settings.maptiler_api_key"
					@update:model-value="onMaptilerApiKeyChange"
					@trailing-button-click="saveApiKey('')">
					<KeyIcon :size="20" />
				</NcTextField>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="tile-servers"
				:name="t('gpxpod', 'Tile servers')"
				:title="t('gpxpod', 'Tile servers')"
				class="app-settings-section">
				<div v-if="!isPublicPage" class="app-settings-section__hint with-icon">
					<InformationOutlineIcon :size="24" class="icon" />
					{{ t('gpxpod', 'Changes are effective after reloading the page.') }}
				</div>
				<TileServerList
					:tile-servers="settings.extra_tile_servers"
					:is-admin="false"
					:read-only="isPublicPage" />
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="about"
				:name="t('gpxpod', 'About')"
				:title="t('gpxpod', 'About')"
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
import ArrowSplitVerticalIcon from 'vue-material-design-icons/ArrowSplitVertical.vue'
import OpacityIcon from 'vue-material-design-icons/Opacity.vue'
import MinusIcon from 'vue-material-design-icons/Minus.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import ViewCompactOutlineIcon from 'vue-material-design-icons/ViewCompactOutline.vue'
import ChartAreasplineVariantIcon from 'vue-material-design-icons/ChartAreasplineVariant.vue'
import FormatSizeIcon from 'vue-material-design-icons/FormatSize.vue'
import RectangleOutlineIcon from 'vue-material-design-icons/RectangleOutline.vue'
import MapMarkerCircleIcon from 'vue-material-design-icons/MapMarkerCircle.vue'
import ImageIcon from 'vue-material-design-icons/Image.vue'
import CursorDefaultClickOutlineIcon from 'vue-material-design-icons/CursorDefaultClickOutline.vue'
import RulerIcon from 'vue-material-design-icons/Ruler.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import AdminIcon from './icons/AdminIcon.vue'

import TileServerList from './TileServerList.vue'

import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { delay } from '../utils.js'
import { subscribe, unsubscribe, emit } from '@nextcloud/event-bus'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import {
	// getFilePickerBuilder,
	// showError,
	showSuccess,
} from '@nextcloud/dialogs'

export default {
	name: 'GpxpodSettingsDialog',

	components: {
		TileServerList,
		AdminIcon,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcCheckboxRadioSwitch,
		NcTextField,
		KeyIcon,
		OpenInNewIcon,
		RulerIcon,
		FilterIcon,
		RectangleOutlineIcon,
		MapMarkerCircleIcon,
		ImageIcon,
		CursorDefaultClickOutlineIcon,
		PaletteIcon,
		ChartAreasplineVariantIcon,
		FormatSizeIcon,
		InformationOutlineIcon,
		ViewCompactOutlineIcon,
		MinusIcon,
		ArrowRightIcon,
		OpacityIcon,
		ArrowSplitVerticalIcon,
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
			return t('gpxpod', 'If your admin hasn\'t defined an API key, you can get one for free on {maptilerLink}. Create an account then go to "Account" -> "API keys" and create a key or use your default one.', { maptilerLink }, null, { escape: false, sanitize: false })
		},
		adminApiKeyHint() {
			const adminLink = '<a href="' + this.adminSettingsUrl + '" target="blank">' + t('gpxpod', 'GpxPod admin settings') + '</a>'
			return t('gpxpod', 'As you are an administrator, you can set global API keys in the {adminLink}', { adminLink }, null, { escape: false, sanitize: false })
		},
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
	},

	unmounted() {
		unsubscribe('show-settings', this.handleShowSettings)
	},

	methods: {
		handleShowSettings() {
			this.showSettings = true
		},
		onMaptilerApiKeyChange(value) {
			delay(() => {
				this.saveApiKey(value)
			}, 2000)()
		},
		saveApiKey(value) {
			this.$emit('save-options', {
				maptiler_api_key: value,
			})
			showSuccess(t('gpxpod', 'API key saved, effective after a page reload'))
		},
		onCheckboxChanged(newValue, key) {
			this.$emit('save-options', { [key]: newValue ? '1' : '0' })
			if (key === 'compact_mode') {
				emit('resize-map')
			}
		},
		onInputChange(e, key) {
			this.$emit('save-options', { [key]: e.target.value })
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

.checkbox-inner {
	display: flex;
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
		&.with-icon {
			display: flex;
			align-items: center;
			.icon {
				margin-right: 8px;
			}
		}
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
		margin: 8px 0;
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

	#arrows-spacing,
	#arrows-scale,
	#line-width,
	#line-opacity,
	#fontsize,
	#exaggeration {
		-webkit-appearance: initial;
	}

	:deep(.checkbox-radio-switch__label-text) {
		display: flex;
	}
}

/*
::v-deep .gpxpod-settings-dialog .modal-container {
	display: flex !important;
}
*/
</style>
