<template>
	<div class="share-item">
		<div class="avatardiv link-icon">
			<LinkVariantIcon :size="20" />
		</div>
		<span class="username">
			<span>{{ t('gpxpod', 'Share link') + (share.label ? ' (' + share.label + ')' : '') }}</span>
		</span>

		<NcActions>
			<NcActionLink
				:href="gpxpodPublicLink"
				target="_blank"
				@click.stop.prevent="copyLink">
				{{ linkCopied ? t('gpxpod', 'Link copied') : t('gpxpod', 'Copy link to clipboard') }}
				<template #icon>
					<ClipboardCheckOutlineIcon v-if="linkCopied"
						class="success"
						:size="20" />
					<ContentCopyIcon v-else
						:size="16" />
				</template>
			</NcActionLink>
		</NcActions>
		<NcActions>
			<NcActionButton
				@click.stop.prevent="clickIframeCopy">
				{{ iframeCopied ? t('gpxpod', 'HTML iframe copied') : t('gpxpod', 'Copy HTML iframe to clipboard (to embed in other websites)') }}
				<template #icon>
					<ApplicationBracketsIcon v-if="iframeCopied"
						class="success"
						:size="20" />
					<ApplicationBracketsOutlineIcon v-else
						:size="20" />
				</template>
			</NcActionButton>
		</NcActions>

		<NcActions
			:force-menu="true"
			placement="bottom">
			<NcActionInput
				type="text"
				:model-value="share.label"
				@submit="submitLabel">
				<template #icon>
					<TextBoxIcon :size="20" />
				</template>
				{{ t('gpxpod', 'Share label') }}
			</NcActionInput>
			<NcActionCheckbox
				:model-value="share.password !== null && share.password !== ''"
				@check="onPasswordCheck"
				@uncheck="onPasswordUncheck">
				{{ t('gpxpod', 'Password protect') }}
			</NcActionCheckbox>
			<NcActionInput
				v-if="share.password !== null"
				type="password"
				:model-value="share.password"
				@submit="submitPassword">
				<template #icon>
					<LockIcon :size="20" />
				</template>
				{{ t('gpxpod', 'Set link password') }}
			</NcActionInput>
			<NcActionSeparator />
			<NcActionButton @click="$emit('delete')">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('gpxpod', 'Delete link') }}
			</NcActionButton>
			<NcActionButton
				:close-after-click="true"
				@click="$emit('add')">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('gpxpod', 'Add another link') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import ApplicationBracketsOutlineIcon from 'vue-material-design-icons/ApplicationBracketsOutline.vue'
import ApplicationBracketsIcon from 'vue-material-design-icons/ApplicationBrackets.vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import TextBoxIcon from 'vue-material-design-icons/TextBox.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'

import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { Timer } from '../utils.js'
import { PUBLIC_LINK_SETTING_KEYS } from '../constants.js'

export default {
	name: 'ShareItem',

	components: {
		NcActions,
		NcActionButton,
		NcActionInput,
		NcActionCheckbox,
		NcActionLink,
		NcActionSeparator,
		ClipboardCheckOutlineIcon,
		LockIcon,
		DeleteIcon,
		PlusIcon,
		TextBoxIcon,
		LinkVariantIcon,
		ApplicationBracketsOutlineIcon,
		ApplicationBracketsIcon,
		ContentCopyIcon,
	},

	props: {
		share: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			linkCopied: false,
			iframeCopied: false,
		}
	},

	computed: {
		gpxpodPublicLink() {
			const link = window.location.protocol + '//' + window.location.host + generateUrl('/apps/gpxpod/s/' + this.share.token)
			const params = {}
			PUBLIC_LINK_SETTING_KEYS.forEach((key) => {
				if (this.settings[key]) {
					params[key] = this.settings[key]
				}
			})
			return link + '?' + new URLSearchParams(params).toString()
		},
		gpxpodIframe() {
			const publicLink = this.gpxpodPublicLink + '?embedded=1'
			return '<iframe src="' + publicLink + '" width="800px" height="600px" allow="fullscreen" />'
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		async copyLink() {
			const publicLink = this.gpxpodPublicLink
			try {
				await navigator.clipboard.writeText(publicLink)
				this.linkCopied = true
				// eslint-disable-next-line
				new Timer(() => {
					this.linkCopied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('gpxpod', 'Link could not be copied to clipboard'))
			}
		},
		async clickIframeCopy(share) {
			const iframe = this.gpxpodIframe
			try {
				await navigator.clipboard.writeText(iframe)
				this.iframeCopied = true
				// eslint-disable-next-line
				new Timer(() => {
					this.iframeCopied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('gpxpod', 'Link could not be copied to clipboard'))
			}
		},
		onPasswordCheck() {
			this.$emit('update:share', {
				...this.share,
				password: '',
			})
		},
		onPasswordUncheck() {
			this.savePassword('')
		},
		submitPassword(e) {
			const password = e.target[0].value
			this.savePassword(password)
		},
		savePassword(password) {
			this.editSharedAccess(null, password).then((response) => {
				if (password === '') {
					this.$emit('update:share', {
						...this.share,
						password: null,
					})
				} else {
					this.$emit('update:share', {
						...this.share,
						password,
					})
				}
				showSuccess(t('gpxpod', 'Share link saved'))
			}).catch((error) => {
				showError(t('gpxpod', 'Failed to edit share link'))
				console.error(error)
			})
		},
		submitLabel(e) {
			const label = e.target[0].value
			this.editSharedAccess(label, null).then((response) => {
				this.$emit('update:share', {
					...this.share,
					label,
				})
				showSuccess(t('gpxpod', 'Share link saved'))
			}).catch((error) => {
				showError(t('gpxpod', 'Failed to edit share link'))
				console.error(error)
			})
		},
		editSharedAccess(label = null, password = null) {
			const url = generateOcsUrl('apps/files_sharing/api/v1/shares/{shareId}', { shareId: this.share.id })
			const params = {
				label: label === null ? undefined : label,
				password: password === null ? undefined : password,
			}
			return axios.put(url, params)
		},
	},
}
</script>

<style scoped lang="scss">
.share-item {
	display: flex;
	align-items: center;

	.success {
		color: var(--color-text-success);
	}

	.username {
		padding: 12px 9px;
		flex-grow: 1;
	}

	.avatardiv {
		background-color: #f5f5f5;
		border-radius: 16px;
		width: 32px;
		height: 32px;

		&.link-icon {
			background-color: var(--color-primary);
			color: white;
			display: flex;
			align-items: center;
			padding: 6px 6px 6px 6px;
		}
	}
}
</style>
