<template>
	<AppNavigationItem
		:class="{ trackItem: true, selectedTrack: track.isEnabled }"
		:title="track.name"
		:editable="false"
		:force-menu="true"
		:menu-open="menuOpen"
		@update:menuOpen="onUpdateMenuOpen"
		@mouseenter.native="onMouseover"
		@mouseleave.native="onMouseout"
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
					:display-name="track.name" />
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
					@click="onZoomClick">
					<template #icon>
						<MagnifyExpand :size="20" />
					</template>
					{{ t('gpxpod', 'Zoom to bounds') }}
				</ActionButton>
				<ActionButton
					@click="onDetailClick">
					<template #icon>
						<InformationOutlineIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Details') }}
				</ActionButton>
				<ActionButton
					@click="onShareClick">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('gpxpod', 'Share') }}
				</ActionButton>
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
import MagnifyExpand from 'vue-material-design-icons/MagnifyExpand'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import Palette from 'vue-material-design-icons/Palette'
import Brush from 'vue-material-design-icons/Brush'
import DeleteIcon from 'vue-material-design-icons/Delete'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft'
import ClickOutside from 'vue-click-outside'

import ActionRadio from '@nextcloud/vue/dist/Components/ActionRadio'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker'
import ColoredAvatar from './ColoredAvatar'

import { emit } from '@nextcloud/event-bus'
import { delay } from '../utils'
import { COLOR_CRITERIAS } from '../constants'

export default {
	name: 'AppNavigationTrackItem',
	components: {
		AppNavigationItem,
		ActionButton,
		ActionRadio,
		ColorPicker,
		ColoredAvatar,
		Palette,
		DeleteIcon,
		ShareVariantIcon,
		InformationOutlineIcon,
		ChevronLeft,
		Brush,
		MagnifyExpand,
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
		onDetailClick() {
		},
		onShareClick() {
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
