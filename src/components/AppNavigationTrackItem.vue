<template>
	<AppNavigationItem
		:class="{ trackItem: true, selectedTrack: enabled }"
		:title="track.name"
		:editable="true"
		:edit-label="t('gpxpod', 'Rename track file')"
		:force-menu="false"
		@update:title="onRename"
		@mouseenter.native="onMouseover"
		@mouseleave.native="onMouseout"
		@click="onClick">
		<div v-if="true"
			slot="icon"
			class="trackItemAvatar">
			<ColorPicker ref="col"
				class="app-navigation-entry-bullet-wrapper trackColorPicker"
				:value="track.color"
				@input="updateColor">
				<ColoredAvatar
					class="itemAvatar"
					:color="track.color || '#0693e3'"
					:size="24"
					:disable-menu="true"
					:disable-tooltip="true"
					:is-no-user="true"
					:display-name="track.name" />
			</ColorPicker>
		</div>
		<div v-else
			slot="icon"
			class="trackItemAvatar">
			<ColoredAvatar
				class="itemAvatar"
				:color="track.color || '#0693e3'"
				:size="24"
				:disable-menu="true"
				:disable-tooltip="true"
				:is-no-user="true"
				:display-name="track.name" />
		</div>
		<!--template
			slot="counter">
			<span>{{ balanceCounter }}</span>
		</template-->
		<template v-if="true"
			slot="actions">
			<ActionButton
				class="detailButton"
				@click="onDetailClick">
				<template #icon>
					<InformationOutlineIcon :size="20" />
				</template>
				{{ t('gpxpod', 'Details') }}
			</ActionButton>
			<ActionButton
				class="detailButton"
				@click="onShareClick">
				<template #icon>
					<ShareVariantIcon :size="20" />
				</template>
				{{ t('gpxpod', 'Share') }}
			</ActionButton>
			<ActionButton
				@click="onMenuColorClick">
				<template #icon>
					<PaletteIcon :size="20" />
				</template>
				{{ t('gpxpod', 'Change color') }}
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
	</AppNavigationItem>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import PaletteIcon from 'vue-material-design-icons/Palette'
import DeleteIcon from 'vue-material-design-icons/Delete'
import ClickOutside from 'vue-click-outside'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker'
import ColoredAvatar from './ColoredAvatar'

import { delay } from '../utils'

export default {
	name: 'AppNavigationTrackItem',
	components: {
		AppNavigationItem,
		ActionButton,
		ColorPicker,
		ColoredAvatar,
		PaletteIcon,
		DeleteIcon,
		ShareVariantIcon,
		InformationOutlineIcon,
	},
	directives: {
		ClickOutside,
	},
	props: {
		track: {
			type: Object,
			required: true,
		},
		enabled: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
		}
	},
	computed: {
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
			}, 2000)()
		},
		applyUpdateColor(color) {
			this.$emit('color-changed', color)
		},
		onMenuColorClick() {
			this.$refs.col.$el.querySelector('.trigger').click()
		},
		onDetailClick() {
			this.$emit('criteria-changed', 'elevation')
		},
		onShareClick() {
		},
		onMouseover() {
			this.$emit('hover-in')
		},
		onMouseout() {
			this.$emit('hover-out')
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