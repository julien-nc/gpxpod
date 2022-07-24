<template>
	<Content app-name="gpxpod">
		<GpxpodNavigation
			:directories="state.directories"
			@add-directory="onAddDirectory"
			@open-directory="onOpenDirectory"
			@close-directory="onCloseDirectory" />
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
		onOpenDirectory(path) {
			console.debug('open ' + path)
			console.debug(this.state.directories)
			this.state.directories[path].isOpen = true
			if (this.state.directories[path].tracks.length === 0) {
				this.loadDirectory(path)
			}
		},
		onCloseDirectory(path) {
			console.debug('close ' + path)
			console.debug(this.state.directories)
			this.state.directories[path].isOpen = false
		},
		loadDirectory(path) {
			const req = {
				directoryPath: path,
				processAll: false,
				recursive: false,
			}
			const url = generateUrl('/apps/gpxpod/tracks')
			axios.post(url, req).then((response) => {
				console.debug('TRACKS response', response.data)
				this.state.directories[path].tracks.push(...response.data.tracks)
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to load track information')
					+ ': ' + (error.response?.data?.error ?? '')
				)
			})
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
