<template>
	<NcAvatar v-if="showMe"
		class="avatar"
		:is-no-user="isNoUser"
		:show-user-status="showUserStatus"
		:style="cssVars"
		v-bind="$attrs" />
	<div v-else />
</template>

<script>
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

import { hexToRgb } from '../utils.js'

export default {
	name: 'ColoredAvatar',

	components: {
		NcAvatar,
	},

	props: {
		color: {
			type: String,
			default: '',
		},
		isNoUser: {
			type: Boolean,
			default: false,
		},
		showUserStatus: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			showMe: true,
		}
	},

	computed: {
		cssVars() {
			return {
				'--member-fg-color': this.color === 'gradient' ? 'white' : this.textColor,
				'--member-bg-color': this.color === 'gradient' ? 'unset' : this.color,
				'--member-bg-gradient': this.color === 'gradient' ? 'linear-gradient(to right, blue, green, orange, red)' : 'unset',
			}
		},
		textColor() {
			const rgb = hexToRgb(this.color)
			return rgb.r + rgb.g + rgb.b > 3 * 128
				? 'black'
				: 'white'
		},
	},

	watch: {
		isNoUser(val) {
			// trick to re-render the avatar in case isNoUser changes
			// re-render only if we show the user status (which is what's not rendered correctly)
			if (this.showUserStatus) {
				this.showMe = false
				this.$nextTick(() => {
					this.showMe = true
				})
			}
		},
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.avatar {
	background-color: var(--member-bg-color) !important;
	background-image: var(--member-bg-gradient) !important;
}

:deep(.avatardiv__initials-wrapper) {
	div {
		color: var(--member-fg-color) !important;
		font-weight: bold !important;
	}
}
</style>
