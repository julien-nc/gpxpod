<template>
	<NcAppNavigationItem
		:name="decodedTrackName"
		:title="track.trackpath"
		:class="{ trackItem: true, selectedTrack: track.isEnabled }"
		:loading="track.loading"
		:editable="false"
		:force-menu="true"
		:force-display-actions="true"
		:menu-open="menuOpen"
		@update:menuOpen="onUpdateMenuOpen"
		@mouseenter.native="$emit('hover-in')"
		@mouseleave.native="$emit('hover-out')"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@click="onClick">
		<div v-if="track.isEnabled"
			slot="icon"
			class="trackItemAvatar">
			<NcColorPicker
				class="app-navigation-entry-bullet-wrapper trackColorPicker"
				:value="track.color"
				@input="updateColor">
				<ColoredAvatar
					ref="avatar"
					class="itemAvatar"
					:color="avatarColor"
					:size="24"
					:disable-menu="true"
					:disable-tooltip="true"
					:is-no-user="true"
					:display-name="track.name.replace(' ', '')" />
			</NcColorPicker>
		</div>
		<!--div v-else
			slot="icon"
			class="trackItemAvatar">
			<ColoredAvatar
				class="itemAvatar"
				:color="avatarColor"
				:size="24"
				:disable-menu="true"
				:disable-tooltip="true"
				:is-no-user="true"
				:display-name="track.name" />
		</div-->
		<template slot="actions">
			<template v-if="!criteriaActionsOpen">
				<NcActionButton
					:close-after-click="true"
					@click="$emit('details-click', track.id)">
					<template #icon>
						<InformationOutlineIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Details') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="$emit('share-click', track.id)">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Share') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onZoomClick">
					<template #icon>
						<MagnifyExpandIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Zoom to bounds') }}
				</NcActionButton>
				<NcActionLink
					:close-after-click="true"
					:href="downloadLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Download') }}
				</NcActionLink>
				<NcActionButton
					@click="onMenuColorClick">
					<template #icon>
						<PaletteIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Change color') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="false"
					@click="criteriaActionsOpen = true">
					<template #icon>
						<BrushIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Change color criteria') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="$emit('correct-elevations')">
					<template #icon>
						<ChartAreasplineVariantIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Correct elevations') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="onDeleteTrackClick">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Delete') }}
				</NcActionButton>
			</template>
			<template v-else>
				<NcActionButton :close-after-click="false"
					@click="criteriaActionsOpen = false">
					<template #icon>
						<ChevronLeftIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Back') }}
				</NcActionButton>
				<NcActionRadio v-for="(c, ckey) in (track.colorExtensionCriteria ? {} : COLOR_CRITERIAS)"
					:key="ckey"
					name="criteria"
					:checked="track.colorCriteria === c.id"
					@change="onCriteriaChange(c.id)">
					{{ c.label }}
				</NcActionRadio>
				<NcActionInput :value="track.colorExtensionCriteria"
					:label="t('gpxpod', 'Extension to use as criteria')"
					@submit="onColorExtensionCriteriaChange">
					<template #icon>
						<CogBoxIcon />
					</template>
				</NcActionInput>
			</template>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import CogBoxIcon from 'vue-material-design-icons/CogBox.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import MagnifyExpandIcon from 'vue-material-design-icons/MagnifyExpand.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import BrushIcon from 'vue-material-design-icons/Brush.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import ChartAreasplineVariantIcon from 'vue-material-design-icons/ChartAreasplineVariant.vue'

import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcActionRadio from '@nextcloud/vue/dist/Components/NcActionRadio.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcColorPicker from '@nextcloud/vue/dist/Components/NcColorPicker.js'
import ColoredAvatar from './ColoredAvatar.vue'

import { emit } from '@nextcloud/event-bus'
import { delay } from '../utils.js'
import { COLOR_CRITERIAS } from '../constants.js'
import { generateUrl } from '@nextcloud/router'
import ClickOutside from 'vue-click-outside'

export default {
	name: 'AppNavigationTrackItem',
	components: {
		NcAppNavigationItem,
		NcActionButton,
		NcActionRadio,
		NcActionLink,
		NcActionInput,
		NcColorPicker,
		ColoredAvatar,
		PaletteIcon,
		DeleteIcon,
		ShareVariantIcon,
		InformationOutlineIcon,
		ChevronLeftIcon,
		BrushIcon,
		MagnifyExpandIcon,
		DownloadIcon,
		ChartAreasplineVariantIcon,
		CogBoxIcon,
	},
	directives: {
		ClickOutside,
	},
	inject: ['isPublicPage'],
	props: {
		track: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			menuOpen: false,
			criteriaActionsOpen: false,
			COLOR_CRITERIAS,
		}
	},
	computed: {
		avatarColor() {
			return this.track.colorCriteria === COLOR_CRITERIAS.none.id
				? this.track.color || '#0693e3'
				: 'gradient'
		},
		downloadLink() {
			return generateUrl(
				'/apps/files/ajax/download.php?dir={dir}&files={files}',
				{ dir: this.decodedFolder, files: this.decodedTrackName }
			)
		},
		// to make sure it works with tracks created before the vue rewrite (url-encoded values in the marker)
		decodedTrackName() {
			return decodeURIComponent(this.track.name)
		},
		decodedFolder() {
			return decodeURIComponent(this.track.folder)
		},
	},

	methods: {
		onClick(e) {
			if (e.target.tagName !== 'DIV') {
				this.$emit('click')
			}
		},
		onDeleteTrackClick() {
			emit('delete-track', this.track)
		},
		updateColor(color) {
			delay(() => {
				this.applyUpdateColor(color)
			}, 1000)()
		},
		applyUpdateColor(color) {
			this.$emit('color-changed', color)
		},
		onMenuColorClick() {
			this.menuOpen = false
			if (this.$refs.avatar) {
				this.$refs.avatar.$el.click()
			}
		},
		onZoomClick() {
			emit('zoom-on', { north: this.track.north, south: this.track.south, east: this.track.east, west: this.track.west })
		},
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.criteriaActionsOpen = false
			}
			this.menuOpen = isOpen
		},
		onCriteriaChange(criteria) {
			this.$emit('criteria-changed', { criteria })
			// this.criteriaActionsOpen = false
			// this.menuOpen = false
		},
		onColorExtensionCriteriaChange(e) {
			this.$emit('criteria-changed', { extensionCriteria: e.target[0].value })
		},
	},

}
</script>

<style scoped lang="scss">
.itemAvatar {
	margin-top: 16px;
	margin-right: 2px;
}

:deep(.app-navigation-entry__title) {
	padding: 0 !important;
}
</style>
