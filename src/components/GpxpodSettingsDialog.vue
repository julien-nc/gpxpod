<template>
	<div id="settings-container">
		<NcAppSettingsDialog
			v-model:open="showSettings"
			:name="t('gpxpod', 'GpxPod settings')"
			:show-navigation="true"
			class="gpxpod-settings-dialog"
			container="#settings-container">
			<NcAppSettingsSection
				id="map"
				:name="t('gpxpod', 'Map')"
				class="app-settings-section">
				<template #icon>
					<MapIcon :size="20" />
				</template>
				<NcNoteCard type="info">
					{{ t('gpxpod', 'Choose whether the navigation track list shows all tracks or only the ones located in the current map view.') }}
				</NcNoteCard>
				<NcFormBox>
					<NcFormBoxSwitch
						:model-value="settings.nav_tracks_filter_map_bounds === '1'"
						@update:model-value="onCheckboxChanged($event, 'nav_tracks_filter_map_bounds')">
						<div class="checkbox-inner">
							<FilterIcon :size="20" class="inline-icon" />
							{{ t('gpxpod', 'Filter with map view (dynamic track list)') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch
						:model-value="settings.nav_show_hovered_dir_bounds === '1'"
						@update:model-value="onCheckboxChanged($event, 'nav_show_hovered_dir_bounds')">
						<div class="checkbox-inner">
							<RectangleOutlineIcon :size="20" class="inline-icon" />
							{{ t('gpxpod', 'Show directory bounds on hover') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch
						:model-value="settings.global_track_colorization === '1'"
						@update:model-value="onCheckboxChanged($event, 'global_track_colorization')">
						<div class="checkbox-inner">
							<PaletteIcon :size="20" class="inline-icon" />
							{{ t('gpxpod', 'Use all the segments in a track to define the color gradient (instead of having independent segments)') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch
						:model-value="settings.show_marker_cluster === '1'"
						@update:model-value="onCheckboxChanged($event, 'show_marker_cluster')">
						<div class="checkbox-inner">
							<MapMarkerCircleIcon :size="20" class="inline-icon" />
							{{ t('gpxpod', 'Show track marker cluster') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch
						:model-value="settings.show_picture_cluster === '1'"
						@update:model-value="onCheckboxChanged($event, 'show_picture_cluster')">
						<div class="checkbox-inner">
							<ImageIcon :size="20" class="inline-icon" />
							{{ t('gpxpod', 'Show picture marker cluster') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch
						:model-value="settings.show_mouse_position_control === '1'"
						@update:model-value="onCheckboxChanged($event, 'show_mouse_position_control')">
						<div class="checkbox-inner">
							<CursorDefaultClickOutlineIcon :size="20" class="inline-icon" />
							{{ t('gpxpod', 'Show mouse position coordinates in the bottom-left map corner') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch
						:model-value="settings.compact_mode === '1'"
						@update:model-value="onCheckboxChanged($event, 'compact_mode')">
						<div class="checkbox-inner">
							<ViewCompactOutlineIcon :size="20" class="inline-icon" />
							{{ t('gpxpod', 'Compact navigation view') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch
						:model-value="settings.line_border === '1'"
						@update:model-value="onCheckboxChanged($event, 'line_border')">
						<div class="checkbox-inner">
							<MinusIcon :size="20" class="inline-icon" />
							{{ t('gpxpod', 'Draw line borders') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch
						:model-value="settings.direction_arrows === '1'"
						@update:model-value="onCheckboxChanged($event, 'direction_arrows')">
						<div class="checkbox-inner">
							<ArrowRightIcon :size="20" class="inline-icon" />
							{{ t('gpxpod', 'Draw line direction arrows') }}
						</div>
					</NcFormBoxSwitch>
					<NcInputField
						:model-value="settings.arrows_scale_factor"
						type="number"
						:label="t('gpxpod', 'Arrows scale factor')"
						min="0.1"
						max="2"
						step="0.1"
						:show-trailing-button="![1, '1'].includes(settings.arrows_scale_factor)"
						@update:model-value="onComponentInputChange($event, 'arrows_scale_factor')"
						@trailing-button-click="onComponentInputChange('1', 'arrows_scale_factor')">
						<template #icon>
							<ArrowRightIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('gpxpod', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<NcInputField
						:model-value="settings.arrows_spacing"
						type="number"
						:label="t('gpxpod', 'Arrows spacing')"
						min="10"
						max="400"
						step="1"
						:show-trailing-button="![200, '200'].includes(settings.arrows_spacing)"
						@update:model-value="onComponentInputChange($event, 'arrows_spacing')"
						@trailing-button-click="onComponentInputChange('200', 'arrows_spacing')">
						<template #icon>
							<ArrowRightIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('gpxpod', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<NcInputField
						:model-value="settings.line_width"
						type="number"
						:label="t('gpxpod', 'Track line width')"
						min="1"
						max="20"
						step="0.5"
						:show-trailing-button="![5, '5'].includes(settings.line_width)"
						@update:model-value="onComponentInputChange($event, 'line_width')"
						@trailing-button-click="onComponentInputChange('5', 'line_width')">
						<template #icon>
							<ArrowSplitVerticalIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('gpxpod', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<NcInputField
						:model-value="settings.line_opacity"
						type="number"
						:label="t('gpxpod', 'Track line opacity')"
						min="0"
						max="1"
						step="0.1"
						:show-trailing-button="![1, '1'].includes(settings.line_opacity)"
						@update:model-value="onComponentInputChange($event, 'line_opacity')"
						@trailing-button-click="onComponentInputChange('1', 'line_opacity')">
						<template #icon>
							<OpacityIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('gpxpod', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<NcSelect
						:model-value="selectedDistanceUnit"
						class="select"
						:input-label="t('gpxpod', 'Distance unit')"
						:options="Object.values(distanceUnitOptions)"
						:no-wrap="true"
						label="label"
						:clearable="false"
						@update:model-value="onComponentInputChange($event.value, 'distance_unit')" />
					<NcInputField
						:model-value="settings.terrainExaggeration"
						type="number"
						:label="t('gpxpod', '3D elevation exaggeration (effective after page reload)')"
						min="0.1"
						max="10"
						step="0.1"
						:show-trailing-button="![2.5, '2.5'].includes(settings.terrainExaggeration)"
						@update:model-value="onComponentInputChange($event, 'terrainExaggeration')"
						@trailing-button-click="onComponentInputChange('2.5', 'terrainExaggeration')">
						<template #icon>
							<ChartAreasplineVariantIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('gpxpod', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<NcInputField
						:model-value="settings.fontScale"
						type="number"
						:label="t('gpxpod', 'Font scale factor (%)')"
						min="80"
						max="120"
						step="1"
						:show-trailing-button="![100, '100'].includes(settings.fontScale)"
						@update:model-value="onComponentInputChange($event, 'fontScale')"
						@trailing-button-click="onComponentInputChange('100', 'fontScale')">
						<template #icon>
							<FormatSizeIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('gpxpod', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
				</NcFormBox>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!isPublicPage"
				id="api-keys"
				:name="t('gpxpod', 'API keys')"
				class="app-settings-section">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
				<div class="notecards">
					<NcNoteCard type="info">
						{{ t('gpxpod', 'If you leave the Maptiler API key empty, GpxPod will use the one defined by the Nextcloud admin as default.') }}
					</NcNoteCard>
					<NcNoteCard v-if="isAdmin" type="info">
						<template #icon>
							<AdminIcon :size="20" />
						</template>
						<span v-html="adminApiKeyHint" />
					</NcNoteCard>
					<NcNoteCard type="info">
						<div v-html="maptilerHint" />
					</NcNoteCard>
				</div>
				<NcTextField
					:model-value="settings.maptiler_api_key"
					:label="t('gpxpod', 'API key to use Maptiler (for vector tile servers)')"
					type="password"
					:placeholder="t('gpxpod', 'my-api-key')"
					:show-trailing-button="!!settings.maptiler_api_key"
					@update:model-value="onMaptilerApiKeyChange"
					@trailing-button-click="saveApiKey('')">
					<template #icon>
						<KeyOutlineIcon :size="20" />
					</template>
				</NcTextField>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="tile-servers"
				:name="t('gpxpod', 'Tile servers')"
				class="app-settings-section">
				<template #icon>
					<MapLegendIcon :size="20" />
				</template>
				<div class="notecards">
					<NcNoteCard v-if="!isPublicPage" type="info">
						{{ t('gpxpod', 'Changes are effective after reloading the page.') }}
					</NcNoteCard>
				</div>
				<TileServerList
					:tile-servers="settings.extra_tile_servers"
					:is-admin="false"
					:read-only="isPublicPage" />
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="about"
				:name="t('gpxpod', 'About')">
				<template #icon>
					<InformationOutlineIcon :size="20" />
				</template>
				<div class="about">
					<label>
						{{ '♥ ' + t('gpxpod', 'Thanks for using Gpxpod') + ' ♥ (v' + settings.app_version + ')' }}
					</label>
					<NcFormBox>
						<NcFormBoxButton
							:label="t('gpxpod', 'Bug/issue tracker')"
							description="https://github.com/julien-nc/gpxpod/issues"
							href="https://github.com/julien-nc/gpxpod/issues"
							target="_blank" />
						<NcFormBoxButton
							:label="t('gpxpod', 'Translation')"
							description="https://crowdin.com/project/gpxpod"
							href="https://crowdin.com/project/gpxpod"
							target="_blank" />
						<NcFormBoxButton
							:label="t('gpxpod', 'User documentation')"
							description="https://github.com/julien-nc/gpxpod/blob/main/docs/user.md"
							href="https://github.com/julien-nc/gpxpod/blob/main/docs/user.md"
							target="_blank" />
						<NcFormBoxButton
							:label="t('gpxpod', 'Admin documentation')"
							description="https://github.com/julien-nc/gpxpod/blob/main/docs/admin.md"
							href="https://github.com/julien-nc/gpxpod/blob/main/docs/admin.md"
							target="_blank" />
						<NcFormBoxButton
							:label="t('gpxpod', 'Developer documentation')"
							description="https://github.com/julien-nc/gpxpod/blob/main/docs/dev.md"
							href="https://github.com/julien-nc/gpxpod/blob/main/docs/dev.md"
							target="_blank" />
					</NcFormBox>
				</div>
			</NcAppSettingsSection>
		</NcAppSettingsDialog>
	</div>
</template>

<script lang="ts">
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
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import MapLegendIcon from 'vue-material-design-icons/MapLegend.vue'
import MapIcon from 'vue-material-design-icons/Map.vue'
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'

import AdminIcon from './icons/AdminIcon.vue'

import TileServerList from './TileServerList.vue'

import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'

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
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcTextField,
		NcNoteCard,
		NcFormBox,
		NcFormBoxSwitch,
		NcInputField,
		NcSelect,
		NcFormBoxButton,
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
		UndoIcon,
		MapIcon,
		MapLegendIcon,
		KeyOutlineIcon,
		AdminIcon,
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
			adminSettingsUrl: generateUrl('/settings/admin/gpxpod#gpxpod_prefs'),
			distanceUnitOptions: {
				metric: {
					label: t('gpxpod', 'Metric'),
					value: 'metric',
				},
				imperial: {
					label: t('gpxpod', 'Imperial (English)'),
					value: 'imperial',
				},
				nautical: {
					label: t('gpxpod', 'Nautical'),
					value: 'nautical',
				},
			},
		}
	},

	computed: {
		selectedDistanceUnit(): Object {
			return this.distanceUnitOptions[this.settings.distance_unit] ?? this.distanceUnitOptions.metric
		},
		maptilerHint(): string {
			const maptilerLink = '<a href="https://maptiler.com" class="external" target="blank">https://maptiler.com</a>'
			return t('gpxpod', 'If your admin hasn\'t defined an API key, you can get one for free on {maptilerLink}. Create an account then go to "Account" -> "API keys" and create a new API key or use your default one.', { maptilerLink }, null, { escape: false, sanitize: false })
		},
		adminApiKeyHint(): string {
			const adminLink = '<a href="' + this.adminSettingsUrl + '" class="external" target="blank">' + t('gpxpod', 'GpxPod admin settings') + '</a>'
			return t('gpxpod', 'As you are an administrator, you can set a global MapTiler API key in the {adminLink}', { adminLink }, null, { escape: false, sanitize: false })
		},
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
	},

	unmounted() {
		unsubscribe('show-settings', this.handleShowSettings)
	},

	methods: {
		handleShowSettings(): void {
			this.showSettings = true
		},
		onMaptilerApiKeyChange(value: string): void {
			delay(() => {
				this.saveApiKey(value)
			}, 2000)()
		},
		saveApiKey(value: string): void {
			this.$emit('save-options', {
				maptiler_api_key: value,
			})
			showSuccess(t('gpxpod', 'API key saved, effective after a page reload'))
		},
		onCheckboxChanged(newValue: boolean, key: string): void {
			this.$emit('save-options', { [key]: newValue ? '1' : '0' })
			if (key === 'compact_mode') {
				emit('resize-map')
			}
		},
		onComponentInputChange(value: string, key: string): void {
			this.$emit('save-options', { [key]: value })
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
	gap: 8px;
}

.app-settings-section {
	.notecards > * {
		margin: 0;
	}

	.notecards,
	.infos {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}
	.about {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
	&.last {
		margin-bottom: 0;
	}
	&__title {
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
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
