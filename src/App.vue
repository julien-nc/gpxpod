<template>
	<Content app-name="gpxpod">
		<GpxpodNavigation
			:directories="state.directories"
			@add-directory="onAddDirectory"
			@add-directory-recursive="onAddDirectoryRecursive"
			@remove-directory="onRemoveDirectory"
			@open-directory="onOpenDirectory"
			@close-directory="onCloseDirectory"
			@track-clicked="onTrackClicked"
			@track-color-changed="onTrackColorChanged"
			@track-criteria-changed="onTrackCriteriaChanged"
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
			directory.tracks = {}
		})
		console.debug('gpxpod state', this.state)
	},

	methods: {
		onAddDirectory(path) {
			const req = {
				path,
			}
			const url = generateUrl('/apps/gpxpod/directory')
			axios.post(url, req).then((response) => {
				this.$set(this.state.directories, response.data, {
					id: response.data,
					path,
					tracks: {},
					isOpen: false,
				})
				console.debug('directories', this.state.directories)
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to add directory')
					+ ': ' + (error.response?.data ?? '')
				)
			})
		},
		onAddDirectoryRecursive(path) {
			const req = {
				path,
				recursive: true,
			}
			const url = generateUrl('/apps/gpxpod/directory')
			axios.post(url, req).then((response) => {
				response.data.forEach((d) => {
					this.$set(this.state.directories, d.id, {
						id: d.id,
						path: d.path,
						tracks: {},
						isOpen: false,
					})
				})
				console.debug('directories', this.state.directories)
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to add directory recursively')
					+ ': ' + (error.response?.data ?? '')
				)
			})
		},
		onRemoveDirectory(id) {
			const directory = this.state.directories[id]
			const req = {
				path: directory.path,
			}
			const url = generateUrl('/apps/gpxpod/deldirectory')
			axios.post(url, req).then((response) => {
				this.$delete(this.state.directories, id)
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to delete directory')
					+ ': ' + (error.response?.data ?? '')
				)
			})
		},
		onOpenDirectory(id) {
			if (Object.keys(this.state.directories[id].tracks).length === 0) {
				this.loadDirectory(id, true)
			} else {
				this.state.directories[id].isOpen = true
			}
		},
		onCloseDirectory(id) {
			this.state.directories[id].isOpen = false
		},
		loadDirectory(id, open = false) {
			const req = {
				directoryPath: this.state.directories[id].path,
				processAll: false,
				recursive: false,
			}
			const url = generateUrl('/apps/gpxpod/tracks')
			axios.post(url, req).then((response) => {
				console.debug('TRACKS response', response.data)
				this.state.directories[id].tracks = response.data.tracks
				if (open) {
					this.state.directories[id].isOpen = true
				}
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to load tracks information')
					+ ': ' + (error.response?.data?.error ?? '')
				)
			})
		},
		onTrackHoverIn({ trackId, dirId }) {
			const track = this.state.directories[dirId].tracks[trackId]
			if (!track.enabled) {
				this.hoveredTrack = track
			} else {
				track.onTop = true
			}
		},
		onTrackHoverOut({ trackId, dirId }) {
			this.hoveredTrack = null
			this.state.directories[dirId].tracks[trackId].onTop = false
		},
		onTrackClicked({ trackId, dirId }) {
			console.debug('track clicked', trackId, dirId)
			const track = this.state.directories[dirId].tracks[trackId]
			if (track.geojson) {
				if (!track.enabled) {
					this.hoveredTrack = null
				}
				track.enabled = !track.enabled
			} else {
				console.debug('no data for ' + trackId)
				this.loadTrack(trackId, dirId)
			}
		},
		onTrackColorChanged({ trackId, dirId, color }) {
			console.debug('color changeeeee', { trackId, dirId, color })
			// if color is there from the beginning, it's reactive
			this.state.directories[dirId].tracks[trackId].color = color
		},
		onTrackCriteriaChanged({ trackId, dirId, criteria }) {
			console.debug('criteria changeeeee', { trackId, dirId, criteria })
			this.state.directories[dirId].tracks[trackId].color_criteria = criteria
		},
		loadTrack(trackId, dirId) {
			// TODO use trackId to load a track instead of the path
			const req = {
				path: this.state.directories[dirId].path + '/' + this.state.directories[dirId].tracks[trackId].name,
			}
			const url = generateUrl('/apps/gpxpod/getGeojson')
			axios.post(url, req).then((response) => {
				this.hoveredTrack = null
				this.state.directories[dirId].tracks[trackId].geojson = response.data
				this.state.directories[dirId].tracks[trackId].enabled = true
				console.debug('LOAD TRACK response', this.state.directories[dirId].tracks[trackId])
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
