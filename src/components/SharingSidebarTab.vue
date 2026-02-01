<template>
	<div class="share-container">
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
			<li v-for="(share, i) in linkShares" :key="share.id">
				<ShareItem
					v-model:share="linkShares[i]"
					:settings="settings"
					@add="addLink"
					@delete="deleteShare(share)" />
			</li>
		</ul>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'

import ShareItem from './ShareItem.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'SharingSidebarTab',

	components: {
		ShareItem,
		NcActions,
		NcActionButton,
		PlusIcon,
		LinkVariantIcon,
	},

	props: {
		path: {
			type: String,
			default: null,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			linkShares: [],
			addingPublicLink: false,
		}
	},

	computed: {
	},

	watch: {
		path() {
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
			const url = generateOcsUrl('apps/files_sharing/api/v1/shares')
			const params = {
				shareType: 3,
				path: this.path,
			}
			axios.post(url, params).then((response) => {
				showSuccess(t('gpxpod', 'Share link created'))
				this.linkShares.push(response.data.ocs.data)
			}).catch((error) => {
				showError(t('gpxpod', 'Failed to create share link'))
				console.error(error)
			})
		},
		deleteShare(share) {
			// to make sure the menu disappears
			this.$refs.shareWithList.click()
			const url = generateOcsUrl('apps/files_sharing/api/v1/shares/{shareId}', { shareId: share.id })
			axios.delete(url).then((response) => {
				const index = this.linkShares.indexOf(share)
				this.linkShares.splice(index, 1)
				showSuccess(t('gpxpod', 'Share link deleted'))
			}).catch((error) => {
				showError(t('gpxpod', 'Failed to delete share'))
				console.error(error)
			})
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
	}
}
</style>
