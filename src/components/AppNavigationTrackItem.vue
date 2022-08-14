<template>
	<AppNavigationItem
		:class="{ trackItem: true, selectedTrack: track.isEnabled }"
		:title="track.name"
		:loading="track.loading"
		:editable="false"
		:force-menu="true"
		:menu-open="menuOpen"
		@update:menuOpen="onUpdateMenuOpen"
		@mouseenter.native="onMouseover"
		@mouseleave.native="onMouseout"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@click="onClick">
		<div v-if="track.isEnabled"
			slot="icon"
			class="trackItemAvatar">
			<ColorPicker ref="col"
				class="app-navigation-entry-bullet-wrapper trackColorPicker"
				:value="track.color"
				@input="updateColor">
				<ColoredAvatar
					class="itemAvatar"
					:color="avatarColor"
					:size="24"
					:disable-menu="true"
					:disable-tooltip="true"
					:is-no-user="true"
					:display-name="track.name.replace(' ', '')" />
			</ColorPicker>
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
		<!--template
			slot="counter">
			<span>{{ balanceCounter }}</span>
		</template-->
		<template v-if="true"
			slot="actions">
			<template v-if="!criteriaActionsOpen">
				<ActionButton
					:close-after-click="true"
					@click="$emit('details-click', track.id)">
					<template #icon>
						<InformationOutlineIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Details') }}
				</ActionButton>
				<ActionButton
					:close-after-click="true"
					@click="$emit('share-click', track.id)">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Share') }}
				</ActionButton>
				<ActionButton
					:close-after-click="true"
					@click="onZoomClick">
					<template #icon>
						<MagnifyExpand :size="20" />
					</template>
					{{ t('gpxpod', 'Zoom to bounds') }}
				</ActionButton>
				<ActionLink
					:close-after-click="true"
					:href="downloadLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Download') }}
				</ActionLink>
				<ActionButton
					@click="onMenuColorClick">
					<template #icon>
						<Palette :size="20" />
					</template>
					{{ t('gpxpod', 'Change color') }}
				</ActionButton>
				<ActionButton :close-after-click="false"
					@click="criteriaActionsOpen = true">
					<template #icon>
						<Brush :size="20" />
					</template>
					{{ t('gpxpod', 'Change color criteria') }}
				</ActionButton>
				<ActionButton
					:close-after-click="true"
					@click="onDeleteTrackClick">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Delete') }}
				</ActionButton>
			</template>
			<template v-else>
				<ActionButton :close-after-click="false"
					@click="criteriaActionsOpen = false">
					<template #icon>
						<ChevronLeft :size="20" />
					</template>
					{{ t('gpxpod', 'Back') }}
				</ActionButton>
				<ActionRadio v-for="(c, cid) in COLOR_CRITERIAS"
					:key="cid"
					name="criteria"
					:checked="track.colorCriteria === c.value"
					@change="onCriteriaChange(c.value)">
					{{ c.label }}
				</ActionRadio>
			</template>
		</template>
	</AppNavigationItem>
</template>

<script>
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import MagnifyExpand from 'vue-material-design-icons/MagnifyExpand.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import Palette from 'vue-material-design-icons/Palette.vue'
import Brush from 'vue-material-design-icons/Brush.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import ClickOutside from 'vue-click-outside'

import ActionLink from '@nextcloud/vue/dist/Components/ActionLink.js'
import ActionRadio from '@nextcloud/vue/dist/Components/ActionRadio.js'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton.js'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem.js'
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker.js'
import ColoredAvatar from './ColoredAvatar.vue'

import { emit } from '@nextcloud/event-bus'
import { delay } from '../utils.js'
import { COLOR_CRITERIAS } from '../constants.js'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'AppNavigationTrackItem',
	components: {
		AppNavigationItem,
		ActionButton,
		ActionRadio,
		ActionLink,
		ColorPicker,
		ColoredAvatar,
		Palette,
		DeleteIcon,
		ShareVariantIcon,
		InformationOutlineIcon,
		ChevronLeft,
		Brush,
		MagnifyExpand,
		DownloadIcon,
	},
	directives: {
		ClickOutside,
	},
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
			return this.track.colorCriteria === COLOR_CRITERIAS.none.value
				? this.track.color || '#0693e3'
				: 'gradient'
		},
		downloadLink() {
			return generateUrl(
				'/apps/files/ajax/download.php?dir={dir}&files={files}',
				{ dir: this.track.folder, files: this.track.name }
			)
		},
	},

	methods: {
		onClick(e) {
			if (e.target.tagName !== 'DIV') {
				this.$emit('click')
			}
		},
		onDeleteTrackClick() {
			this.$emit('delete-track', this.track.id)
		},
		onRename(newName) {
			this.$emit('rename', this.track.id, newName)
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
			this.$refs.col.$el.querySelector('.trigger').click()
		},
		onZoomClick() {
			emit('zoom-on', { north: this.track.north, south: this.track.south, east: this.track.east, west: this.track.west })
		},
		onMouseover() {
			this.$emit('hover-in')
		},
		onMouseout() {
			this.$emit('hover-out')
		},
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.criteriaActionsOpen = false
			}
			this.menuOpen = isOpen
		},
		onCriteriaChange(criteria) {
			this.$emit('criteria-changed', criteria)
			// this.criteriaActionsOpen = false
			// this.menuOpen = false
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
