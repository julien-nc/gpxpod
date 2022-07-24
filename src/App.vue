<template>
	<Content app-name="gpxpod">
		<GpxpodNavigation
			:directories="state.directories"
			@add-directory="onAddDirectory" />
		<AppContent
			:list-max-width="50"
			:list-min-width="20"
			:list-size="20"
			:show-details="false"
			@update:showDetails="a = 2">
			<!--template slot="list">
			</template-->
			<Map ref="map"
				:settings="state.settings"
				@map-state-change="saveOptions" />
		</AppContent>
	</Content>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Content from '@nextcloud/vue/dist/Components/Content'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'

import GpxpodNavigation from './components/GpxpodNavigation'
import Map from './components/map/Map'

export default {
	name: 'App',

	components: {
		GpxpodNavigation,
		Map,
		AppContent,
		Content,
	},

	props: {
	},

	data() {
		return {
			state: loadState('gpxpod', 'gpxpod-state'),
		}
	},

	computed: {
	},

	watch: {
	},

	beforeMount() {
	},

	mounted() {
		console.debug('gpxpod state', this.state)
	},

	methods: {
		onAddDirectory(path) {
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/gpxpod/saveOptionValues')
			axios.put(url, req).then((response) => {
			}).catch((error) => {
				showError(
					t('gpxpod', 'Failed to save settings')
					+ ': ' + (error.response?.data?.error ?? '')
				)
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
body {
	min-height: 100%;
	height: auto;
}
</style>
