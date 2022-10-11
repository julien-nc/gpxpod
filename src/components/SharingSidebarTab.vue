<template>
	<div class="share-container">
		shares of {{ path }}
		<ul
			id="shareWithList"
			ref="shareWithList"
			class="shareWithList">
			<li v-if="linkShares.length === 0"
				class="add-public-link-line"
				@click="addLink">
				<div :class="'avatardiv link-icon' + (addingPublicLink ? ' loading' : '')">
					<LinkVariantIcon :size="20" />
				</div>
				<span class="username">
					{{ t('gpxpod', 'Share link') }}
				</span>
				<NcActions>
					<NcActionButton>
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Create a new share link') }}
					</NcActionButton>
				</NcActions>
			</li>
			<li v-for="share in linkShares" :key="share.id">
				<div class="avatardiv link-icon">
					<LinkVariantIcon :size="20" />
				</div>
				<span class="username">
					<span>{{ t('gpxpod', 'Share link') + (share.label ? ' (' + share.label + ')' : '') }}</span>
				</span>

				<NcActions>
					<NcActionLink
						:href="generateGpxpodPublicLink(share)"
						target="_blank"
						@click.stop.prevent="copyLink(share)">
						{{ linkCopied[share.id] ? t('gpxpod', 'Link copied') : t('gpxpod', 'Copy to clipboard') }}
						<template #icon>
							<ClipboardCheckOutlineIcon v-if="linkCopied[share.id]"
								class="success"
								:size="20" />
							<ClippyIcon v-else
								:size="16" />
						</template>
					</NcActionLink>
				</NcActions>

				<!--NcActions>
					<NcActionLink
						:href="generateGpxpodPublicLink(access)"
						target="_blank"
						@click.stop.prevent="displaygpxpodLinkQRCode(access)">
						<template #icon>
							<QrcodeIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Show QRCode for mobile clients') }}
					</NcActionLink>
				</NcActions-->

				<NcActions
					:force-menu="true"
					placement="bottom">
					<NcActionInput
						type="text"
						:value="share.label"
						@submit="submitLabel(share, $event)">
						<template #icon>
							<TextBoxIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Label') }}
					</NcActionInput>
					<NcActionCheckbox
						:checked="share.password !== null"
						@check="onPasswordCheck(share, $event)"
						@uncheck="onPasswordUncheck(share, $event)">
						{{ t('gpxpod', 'Password protect') }}
					</NcActionCheckbox>
					<NcActionInput
						v-if="share.password !== null"
						type="password"
						:value="share.password"
						@submit="submitPassword(share, $event)">
						<template #icon>
							<LockIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Set link password') }}
					</NcActionInput>
					<NcActionSeparator />
					<NcActionButton @click="clickDeleteShare(share)">
						<template #icon>
							<DeleteIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Delete link') }}
					</NcActionButton>
					<NcActionButton
						:close-after-click="true"
						@click="addLink">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('gpxpod', 'Add another link') }}
					</NcActionButton>
				</NcActions>
			</li>
		</ul>
	</div>
</template>

<script>
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import TextBoxIcon from 'vue-material-design-icons/TextBox.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
// import QrcodeIcon from 'vue-material-design-icons/Qrcode.vue'

import ClippyIcon from './icons/ClippyIcon.vue'

import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'

import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { Timer } from '../utils.js'

export default {
	name: 'SharingSidebarTab',

	components: {
		ClippyIcon,
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
	},

	props: {
		path: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			linkShares: [],
			addingPublicLink: false,
			linkCopied: {},
		}
	},

	computed: {
	},

	watch: {
		track() {
			this.getLinkShares()
		},
	},

	mounted() {
		this.getLinkShares()
	},

	methods: {
		getLinkShares() {
			this.linkShares = []
			const url = generateOcsUrl('apps/files_sharing/api/v1/shares?format=json&reshares=true&path={path}', { path: this.path })
			axios.get(url).then((response) => {
				this.linkShares = response.data.ocs.data.filter(s => s.share_type === 3)
				console.debug('getLinkShares', this.linkShares)
			}).catch((error) => {
				console.error(error)
			})
		},
		addLink() {
			console.debug('addLink TODO')
		},
		generateGpxpodPublicLink(share) {
			return window.location.protocol + '//' + window.location.host + generateUrl('/apps/gpxpod/s/' + share.token)
		},
		async copyLink(share) {
			const publicLink = this.generateGpxpodPublicLink(share)
			try {
				await this.$copyText(publicLink)
				this.$set(this.linkCopied, share.id, true)
				// eslint-disable-next-line
				new Timer(() => {
					this.$set(this.linkCopied, share.id, false)
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('gpxpod', 'Link could not be copied to clipboard.'))
			}
		},
		// TODO adjust to ocs api
		onPasswordCheck(share) {
			this.$set(share, 'password', '')
		},
		onPasswordUncheck(share) {
			this.savePassword(share, '')
		},
		submitPassword(share, e) {
			const password = e.target[1].value
			this.savePassword(share, password)
		},
		savePassword(share, password) {
			/*
			network.editSharedAccess(this.projectId, share, null, password).then((response) => {
				if (password === '') {
					this.$set(share, 'password', null)
				} else {
					this.$set(share, 'password', password)
				}
				showSuccess(t('gpxpod', 'Share link saved'))
			}).catch((error) => {
				showError(
					t('gpxpod', 'Failed to edit share link')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
				console.error(error)
			})
			*/
		},
		submitLabel(share, e) {
			const label = e.target[1].value
			/*
			network.editSharedAccess(this.projectId, share, label, null).then((response) => {
				this.$set(share, 'label', label)
				showSuccess(t('gpxpod', 'Share link saved'))
			}).catch((error) => {
				showError(
					t('gpxpod', 'Failed to edit share link')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
				console.error(error)
			})
			*/
		},
		clickDeleteShare(share) {
			// to make sure the menu disappears
			this.$refs.shareWithList.click()
			/*
			network.deleteAccess(this.projectId, share).then((response) => {
				const index = this.shares.indexOf(share)
				this.shares.splice(index, 1)
			}).catch((error) => {
				showError(
					t('gpxpod', 'Failed to delete share')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
				console.error(error)
			})
			*/
		},
	},
}
</script>

<style scoped lang="scss">
.share-container {
	width: 100%;
	padding: 4px;

	.shareWithList {
		margin-bottom: 20px;
		li {
			display: flex;
			align-items: center;

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
	}
}
</style>