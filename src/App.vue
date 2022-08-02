<template>
	<Content app-name="gpxpod">
		<GpxpodNavigation
			:directories="navigationDirectories"
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
				:show-mouse-position-control="state.settings.show_mouse_position_control === '1'"
				:tracks-to-draw="enabledTracks"
				:directories="state.directories"
				:hovered-track="hoveredTrack"
				:cluster-tracks="clusterTracks"
				@map-bounds-change="storeBounds"
				@map-state-change="saveOptions" />
		</AppContent>
		<GpxpodSettingsDialog
			:settings="state.settings"
			@save-options="saveOptions" />
	</Content>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Content from '@nextcloud/vue/dist/Components/Content'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'

import GpxpodSettingsDialog from './components/GpxpodSettingsDialog'
import GpxpodNavigation from './components/GpxpodNavigation'
import Map from './components/map/Map'

import { COLOR_CRITERIAS } from './constants'

export default {
	name: 'App',

	components: {
		GpxpodNavigation,
		GpxpodSettingsDialog,
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
			mapNorth: null,
			mapEast: null,
			mapSouth: null,
			mapWest: null,
			COLOR_CRITERIAS,
		}
	},

	computed: {
		enabledTracks() {
			const result = []
			Object.values(this.state.directories).forEach((dir) => {
				if (dir.open && dir.tracks) {
					result.push(...Object.values(dir.tracks).filter(t => t.enabled))
				}
			})
			return result
		},
		clusterTracks() {
			const tracks = Object.values(this.state.directories)
				.filter(d => d.open)
				.reduce(
					(acc, directory) => {
						acc.push(...Object.values(directory.tracks))
						return acc
					},
					[]
				)
			console.debug(':::::accumulated tracks', tracks)
			return tracks
		},
		// only keep what crossed the current map view
		navigationDirectories() {
			// we don't filter with map bounds: show averything
			if (this.state.settings.nav_tracks_filter_map_bounds !== '1') {
				return this.state.directories

			} else if (this.mapNorth === null || this.mapEast === null || this.mapSouth === null || this.mapWest === null) {
				// we filter with map bounds and the map didn't report any bounds yet: we show nothing
				return {}
			}
			// we only show those crossing the map bounds
			const res = {}
			Object.keys(this.state.directories).forEach((dirId) => {
				res[dirId] = {
					...this.state.directories[dirId],
					tracks: this.filterTracksCrossingMap(this.state.directories[dirId].tracks),
				}
			})
			return res
		},
	},

	watch: {
	},

	beforeMount() {
		// empty Php array => array instead of object
		if (Array.isArray(this.state.directories)) {
			this.state.directories = {}
		}
	},

	mounted() {
		Object.values(this.state.directories).forEach((directory) => {
			directory.tracks = {}
			if (directory.open) {
				this.loadDirectory(directory.id)
			}
		})
		console.debug('gpxpod state', this.state)
	},

	methods: {
		storeBounds({ north, east, south, west }) {
			this.mapNorth = north
			this.mapEast = east
			this.mapSouth = south
			this.mapWest = west
		},
		filterTracksCrossingMap(tracks) {
			return Object.fromEntries(
				Object.entries(tracks)
					.filter(([trackId, track]) => {
						// solution from https://www.geeksforgeeks.org/find-two-rectangles-overlap/ , having 4 points
						/*
						l1: Top Left coordinate of first rectangle: map nw
						r1: Bottom Right coordinate of first rectangle: map se
						l2: Top Left coordinate of second rectangle: track nw
						r2: Bottom Right coordinate of second rectangle: track se
						// if rectangle has area 0, no overlap
						if (l1.x == r1.x || l1.y == r1.y || r2.x == l2.x || l2.y == r2.y)
							return false
						// If one rectangle is on left side of other
						if (l1.x > r2.x || l2.x > r1.x)
							return false
						// If one rectangle is above other
						if (r1.y > l2.y || r2.y > l1.y)
							return false
						*/

						if (this.mapWest === this.mapEast || this.mapNorth === this.mapSouth || track.west === track.east || track.north === track.south) {
							return false
						}
						if (this.mapWest > track.east || track.west > this.mapEast) {
							return false
						}
						if (this.mapSouth > track.north || track.south > this.mapNorth) {
							return false
						}
						return true
					})
			)
		},
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
					open: false,
				})
				console.debug('[gpxpod] directories', this.state.directories)
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
						open: false,
					})
				})
				console.debug('[gpxpod] directories', this.state.directories)
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
				this.hoveredTrack = null
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
				this.state.directories[id].open = true
				this.updateDirectory(id, { open: true })
			}
		},
		onCloseDirectory(id) {
			this.state.directories[id].open = false
			this.updateDirectory(id, { open: false })
		},
		updateDirectory(id, values) {
			const req = values
			const url = generateUrl('/apps/gpxpod/directories/{id}', { id })
			axios.put(url, req).then((response) => {
				console.debug('update dir', response.data)
			}).catch((error) => {
				console.error(error)
			})
		},
		loadDirectory(id, open = false) {
			const req = {
				id,
				directoryPath: this.state.directories[id].path,
				processAll: false,
			}
			const url = generateUrl('/apps/gpxpod/tracks')
			axios.post(url, req).then((response) => {
				console.debug('[gpxpod] TRACKS response', response.data)
				this.state.directories[id].tracks = response.data.tracks
				if (open) {
					this.state.directories[id].open = true
					this.updateDirectory(id, { open: true })
				}
				// restore track state
				Object.values(this.state.directories[id].tracks).forEach((track) => {
					if (track.enabled) {
						// trick to avoid displaying the simplified track, disable it while we load it
						track.enabled = false
						this.loadTrack(track.id, id, true, false)
					}
				})
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
			const track = this.state.directories[dirId].tracks[trackId]
			console.debug('[gpxpod] track clicked', trackId, dirId, 'enabled', track.enabled)
			if (track.geojson) {
				if (!track.enabled) {
					this.hoveredTrack = null
				}
				track.enabled = !track.enabled
				this.updateTrack(trackId, { enabled: track.enabled })
			} else {
				console.debug('[gpxpod] no data for ' + trackId)
				this.loadTrack(trackId, dirId, true, true)
			}
		},
		onTrackColorChanged({ trackId, dirId, color }) {
			console.debug('[gpxpod] color changeeeee', { trackId, dirId, color })
			this.state.directories[dirId].tracks[trackId].color = color
			this.state.directories[dirId].tracks[trackId].color_criteria = COLOR_CRITERIAS.none.value
			this.updateTrack(trackId, { color, colorCriteria: COLOR_CRITERIAS.none.value })
		},
		onTrackCriteriaChanged({ trackId, dirId, criteria }) {
			console.debug('[gpxpod] criteria changeeeee', { trackId, dirId, criteria })
			this.state.directories[dirId].tracks[trackId].color_criteria = criteria
			this.updateTrack(trackId, { colorCriteria: criteria })
		},
		updateTrack(id, values) {
			const req = values
			const url = generateUrl('/apps/gpxpod/tracks/{id}', { id })
			axios.put(url, req).then((response) => {
				console.debug('update track', response.data)
			}).catch((error) => {
				console.error(error)
			})
		},
		loadTrack(trackId, dirId, enable = false, saveEnable = false) {
			// TODO use trackId to load a track instead of the path
			const req = {
				path: this.state.directories[dirId].path + '/' + this.state.directories[dirId].tracks[trackId].name,
			}
			const url = generateUrl('/apps/gpxpod/getGeojson')
			axios.post(url, req).then((response) => {
				this.hoveredTrack = null
				this.state.directories[dirId].tracks[trackId].geojson = response.data
				if (enable) {
					this.state.directories[dirId].tracks[trackId].enabled = true
					if (saveEnable) {
						this.updateTrack(trackId, { enabled: true })
					}
				}
				console.debug('[gpxpod] LOAD TRACK response', this.state.directories[dirId].tracks[trackId])
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to load track geojson')
					+ ': ' + (error.response?.data?.error ?? '')
				)
			})
		},
		saveOptions(values) {
			Object.assign(this.state.settings, values)
			console.debug('[gpxpod] settings saved', this.state.settings)
			const req = {
				values,
			}
			const url = generateUrl('/apps/gpxpod/saveOptionValues')
			axios.put(url, req).then((response) => {
				const keys = Object.keys(values)
				if (keys.includes('maptiler_api_key') || keys.includes('mapbox_api_key')) {
					location.reload()
				}
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
