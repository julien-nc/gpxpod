<template>
	<Content app-name="gpxpod">
		<GpxpodNavigation
			:directories="state.directories"
			@add-directory="onAddDirectory"
			@open-directory="onOpenDirectory"
			@close-directory="onCloseDirectory"
			@track-clicked="onTrackClicked"
			@track-color-changed="onTrackColorChanged"
			@track-hover-in="onTrackHoverIn"
			@track-hover-out="onTrackHoverOut" />
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
				:tracks="enabledTracks"
				:directories="state.directories"
				:hovered-track="hoveredTrack"
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
			hoveredTrack: null,
		}
	},

	computed: {
		enabledTracks() {
			const result = []
			Object.values(this.state.directories).forEach((dir) => {
				if (dir.tracks) {
					result.push(...Object.values(dir.tracks).filter(t => t.enabled))
				}
			})
			return result
		},
	},

	watch: {
	},

	beforeMount() {
	},

	mounted() {
		Object.values(this.state.directories).forEach((directory) => {
			// this.$set(directory, 'tracks', {})
			directory.tracks = {}
		})
		console.debug('gpxpod state', this.state)
	},

	methods: {
		onAddDirectory(path) {
		},
		onOpenDirectory(path) {
			if (Object.keys(this.state.directories[path].tracks).length === 0) {
				this.loadDirectory(path, true)
			} else {
				this.state.directories[path].isOpen = true
			}
		},
		onCloseDirectory(path) {
			this.state.directories[path].isOpen = false
		},
		loadDirectory(path, open = false) {
			const req = {
				directoryPath: path,
				processAll: false,
				recursive: false,
			}
			const url = generateUrl('/apps/gpxpod/tracks')
			axios.post(url, req).then((response) => {
				console.debug('TRACKS response', response.data)
				// this.$set(this.state.directories[path], 'tracks', response.data.tracks)
				this.state.directories[path].tracks = response.data.tracks
				if (open) {
					this.state.directories[path].isOpen = true
				}
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to load track information')
					+ ': ' + (error.response?.data?.error ?? '')
				)
			})
		},
		onTrackHoverIn({ trackId, path }) {
			const track = this.state.directories[path].tracks[trackId]
			if (!track.enabled) {
				this.hoveredTrack = track
			} else {
				track.onTop = true
			}
		},
		onTrackHoverOut({ trackId, path }) {
			this.hoveredTrack = null
			this.state.directories[path].tracks[trackId].onTop = false
		},
		onTrackClicked({ trackId, path }) {
			console.debug('track clicked', trackId, path)
			const track = this.state.directories[path].tracks[trackId]
			if (track.geojson) {
				if (!track.enabled) {
					this.hoveredTrack = null
				}
				track.enabled = !track.enabled
			} else {
				console.debug('no data for ' + trackId)
				this.loadTrack(trackId, path)
			}
		},
		onTrackColorChanged({ trackId, path, color }) {
			console.debug('color changeeeee', { trackId, path, color })
			// if color is there from the beginning, it's reactive
			this.state.directories[path].tracks[trackId].color = color
			// this.$set(this.state.directories[path].tracks[trackId], 'color', color)
		},
		loadTrack(trackId, path) {
			const req = {
				path: path + '/' + this.state.directories[path].tracks[trackId].name,
			}
			const url = generateUrl('/apps/gpxpod/getGeojson')
			axios.post(url, req).then((response) => {
				this.hoveredTrack = null
				// this.$set(this.state.directories[path].tracks[trackId], 'geojson', response.data)
				// this.$set(this.state.directories[path].tracks[trackId], 'enabled', true)
				this.state.directories[path].tracks[trackId].geojson = response.data
				this.state.directories[path].tracks[trackId].enabled = true
				console.debug('LOAD TRACK response', this.state.directories[path].tracks[trackId])
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to load track geojson')
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
