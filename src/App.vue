<template>
	<NcContent app-name="gpxpod">
		<GpxpodNavigation
			:directories="navigationDirectories"
			@directory-add="onDirectoryAdd"
			@directory-add-recursive="onDirectoryAddRecursive"
			@directory-remove="onDirectoryRemove"
			@directory-zoom="onDirectoryZoom"
			@directory-open="onDirectoryOpen"
			@directory-close="onDirectoryClose"
			@directory-sort-order-changed="onDirectorySortOrderChanged"
			@directory-details-click="onDirectoryDetailsClicked"
			@directory-share-click="onDirectoryShareClicked"
			@directory-hover-in="onDirectoryHoverIn"
			@directory-hover-out="onDirectoryHoverOut"
			@directory-reload="onDirectoryReload"
			@directory-reload-reprocess="onDirectoryReloadReprocess"
			@track-clicked="onTrackClicked"
			@track-details-click="onTrackDetailsClicked"
			@track-share-click="onTrackShareClicked"
			@track-color-changed="onTrackColorChanged"
			@track-criteria-changed="onTrackCriteriaChanged"
			@track-correct-elevations="onTrackCorrectElevations"
			@track-hover-in="onTrackHoverIn"
			@track-hover-out="onTrackHoverOut" />
		<NcAppContent
			:list-max-width="50"
			:list-min-width="20"
			:list-size="20"
			:show-details="false"
			@update:showDetails="a = 2">
			<!--template slot="list">
			</template-->
			<MaplibreMap ref="map"
				:settings="state.settings"
				:show-mouse-position-control="state.settings.show_mouse_position_control === '1'"
				:use-terrain="state.settings.use_terrain === '1'"
				:tracks-to-draw="enabledTracks"
				:directories="state.directories"
				:hovered-track="hoveredTrackToShow"
				:hovered-directory-bounds="hoveredDirectoryBoundsToShow"
				:cluster-tracks="clusterTracks"
				:cluster-pictures="clusterPictures"
				:unit="distanceUnit"
				@map-bounds-change="storeBounds"
				@map-state-change="saveOptions"
				@track-marker-hover-in="onTrackHoverIn"
				@track-marker-hover-out="onTrackHoverOut" />
		</NcAppContent>
		<DirectorySidebar v-if="sidebarDirectory"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			:directory="sidebarDirectory"
			@update:active="onUpdateActiveTab"
			@close="showSidebar = false" />
		<TrackSidebar v-if="sidebarTrack"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			:track="sidebarTrack"
			:settings="state.settings"
			@update:active="onUpdateActiveTab"
			@close="showSidebar = false" />
		<GpxpodSettingsDialog
			:settings="state.settings"
			@save-options="saveOptions" />
	</NcContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'

import GpxpodSettingsDialog from './components/GpxpodSettingsDialog.vue'
import GpxpodNavigation from './components/GpxpodNavigation.vue'

import { COLOR_CRITERIAS } from './constants.js'
import DirectorySidebar from './components/DirectorySidebar.vue'
import TrackSidebar from './components/TrackSidebar.vue'
import MaplibreMap from './components/map/MaplibreMap.vue'

export default {
	name: 'App',

	components: {
		MaplibreMap,
		TrackSidebar,
		DirectorySidebar,
		GpxpodNavigation,
		GpxpodSettingsDialog,
		NcAppContent,
		NcContent,
	},

	provide: {
		isPublicPage: ('shareToken' in loadState('gpxpod', 'gpxpod-state')),
	},

	props: {
	},

	data() {
		return {
			state: loadState('gpxpod', 'gpxpod-state'),
			hoveredTrack: null,
			hoveredDirectory: null,
			mapNorth: null,
			mapEast: null,
			mapSouth: null,
			mapWest: null,
			COLOR_CRITERIAS,
			showSidebar: false,
			activeSidebarTab: '',
			sidebarTrack: null,
			sidebarDirectory: null,
		}
	},

	computed: {
		isPublicPage() {
			return ('shareToken' in this.state)
		},
		distanceUnit() {
			return this.state.settings.distance_unit ?? 'metric'
		},
		enabledTracks() {
			const result = []
			Object.values(this.state.directories).forEach((dir) => {
				if (dir.isOpen && dir.tracks) {
					result.push(...Object.values(dir.tracks).filter(t => t.isEnabled))
				}
			})
			return result
		},
		clusterTracks() {
			const tracks = Object.values(this.state.directories)
				.filter(d => d.isOpen)
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
		clusterPictures() {
			const pictures = Object.values(this.state.directories)
				.filter(d => d.isOpen)
				.reduce(
					(acc, directory) => {
						acc.push(...Object.values(directory.pictures))
						return acc
					},
					[]
				)
			console.debug(':::::accumulated pictures', pictures)
			return pictures
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
		// only show hovered track if it's not already enabled (hence visible)
		hoveredTrackToShow() {
			if (this.hoveredTrack?.isEnabled) {
				return null
			}
			return this.hoveredTrack
		},
		// hovering a track also emits the dir hover event
		// avoid dir bounds display if a track is currently hovered
		hoveredDirectoryBoundsToShow() {
			if (this.state.settings.nav_show_hovered_dir_bounds !== '1'
				|| this.hoveredTrack !== null
				|| this.hoveredDirectory === null) {
				return null
			}

			const tracksArray = Object.values(this.hoveredDirectory.tracks)
			if (tracksArray.length === 0) {
				return null
			}
			return this.getDirectoryBounds(this.hoveredDirectory.id)
		},
	},

	watch: {
		showSidebar(newValue) {
			emit('sidebar-toggled')
		},
	},

	beforeMount() {
		// empty Php array => array instead of object
		if (Array.isArray(this.state.directories)) {
			this.state.directories = {}
		}

		if (this.isPublicPage) {
			this.state.settings.initialBounds = this.getDirectoryBounds(this.state.shareToken)
			if (this.state.shareTargetType === 'folder') {
				this.loadPublicDirectory()
			}
		} else {
			Object.values(this.state.directories).forEach((directory) => {
				directory.tracks = {}
				directory.pictures = {}
				if (directory.isOpen) {
					this.loadDirectory(directory.id)
				}
			})
		}
		console.debug('gpxpod state', this.state)
	},

	mounted() {
		subscribe('save-settings', this.saveOptions)
	},

	beforeDestroy() {
		unsubscribe('save-settings', this.saveOptions)
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
		onDirectoryAdd(path) {
			const req = {
				path,
			}
			const url = generateUrl('/apps/gpxpod/directories')
			axios.post(url, req).then((response) => {
				this.$set(this.state.directories, response.data, {
					id: response.data,
					path,
					tracks: {},
					isOpen: false,
					loading: false,
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
		onDirectoryAddRecursive(path) {
			const req = {
				path,
				recursive: true,
			}
			const url = generateUrl('/apps/gpxpod/directories')
			axios.post(url, req).then((response) => {
				response.data.forEach((d) => {
					this.$set(this.state.directories, d.id, {
						id: d.id,
						path: d.path,
						tracks: {},
						isOpen: false,
						loading: false,
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
		onDirectoryRemove(dirId) {
			const url = generateUrl('/apps/gpxpod/directories/{dirId}', { dirId })
			axios.delete(url).then((response) => {
				this.$delete(this.state.directories, dirId)
				this.hoveredTrack = null
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to delete directory')
					+ ': ' + (error.response?.data ?? '')
				)
			})
		},
		onDirectoryZoom(dirId) {
			const tracksArray = Object.values(this.state.directories[dirId].tracks)
			if (tracksArray.length === 0) {
				return
			}
			emit('zoom-on', this.getDirectoryBounds(dirId))
		},
		onDirectoryHoverIn(dirId) {
			this.hoveredDirectory = this.state.directories[dirId]
		},
		onDirectoryHoverOut(dirId) {
			this.hoveredDirectory = null
		},
		getDirectoryBounds(dirId) {
			const tracksArray = Object.values(this.state.directories[dirId].tracks)
			let north = tracksArray[0].north
			let east = tracksArray[0].east
			let south = tracksArray[0].south
			let west = tracksArray[0].west
			for (let i = 1; i < tracksArray.length; i++) {
				const t = tracksArray[i]
				if (t.north > north) {
					north = t.north
				}
				if (t.south < south) {
					south = t.south
				}
				if (t.east > east) {
					east = t.east
				}
				if (t.west < west) {
					west = t.west
				}
			}
			return { north, south, east, west }
		},
		onDirectoryOpen(dirId) {
			if (Object.keys(this.state.directories[dirId].tracks).length === 0) {
				this.loadDirectory(dirId, true)
			} else {
				this.state.directories[dirId].isOpen = true
				this.updateDirectory(dirId, { isOpen: true })
			}
		},
		onDirectoryClose(dirId) {
			this.state.directories[dirId].isOpen = false
			this.updateDirectory(dirId, { isOpen: false })
		},
		onDirectoryReload(dirId) {
			this.loadDirectory(dirId, true)
		},
		onDirectoryReloadReprocess(dirId) {
			this.loadDirectory(dirId, true, true)
		},
		onDirectorySortOrderChanged({ dirId, sortOrder }) {
			this.state.directories[dirId].sortOrder = sortOrder
			this.updateDirectory(dirId, { sortOrder })
		},
		updateDirectory(dirId, values) {
			const req = values
			const url = generateUrl('/apps/gpxpod/directories/{dirId}', { dirId })
			axios.put(url, req).then((response) => {
				console.debug('update dir', response.data)
			}).catch((error) => {
				console.error(error)
			})
		},
		loadPublicDirectory() {
			Object.values(this.state.directories[this.state.shareToken].tracks).forEach((track) => {
				if (track.isEnabled) {
					// trick to avoid displaying the simplified track, disable it while we load it
					track.isEnabled = false
					this.loadPublicTrack(track.id, true, false)
				}
			})
		},
		loadDirectory(dirId, open = false, processAll = false) {
			this.state.directories[dirId].loading = true
			const req = {
				directoryPath: this.state.directories[dirId].path,
				processAll,
			}
			const url = generateUrl('/apps/gpxpod/directories/{dirId}/tracks', { dirId })
			axios.post(url, req).then((response) => {
				console.debug('[gpxpod] TRACKS response', response.data)
				this.state.directories[dirId].tracks = response.data.tracks
				if (Object.keys(response.data.pictures).length === 0) {
					this.state.directories[dirId].pictures = {}
				} else {
					this.state.directories[dirId].pictures = response.data.pictures
				}
				if (open) {
					this.state.directories[dirId].isOpen = true
					this.updateDirectory(dirId, { isOpen: true })
				}
				// restore track state
				Object.values(this.state.directories[dirId].tracks).forEach((track) => {
					if (track.isEnabled) {
						// trick to avoid displaying the simplified track, disable it while we load it
						track.isEnabled = false
						this.loadTrack(track.id, dirId, true, false)
					}
				})
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to load tracks information')
					+ ': ' + (error.response?.data?.error ?? '')
				)
			}).then(() => {
				this.state.directories[dirId].loading = false
			})
		},
		onTrackHoverIn({ trackId, dirId }) {
			console.debug('[gpxpod] hover on', trackId, dirId)
			const track = this.state.directories[dirId].tracks[trackId]
			if (track.isEnabled) {
				track.onTop = true
			}
			this.hoveredTrack = track
		},
		onTrackHoverOut({ trackId, dirId }) {
			this.hoveredTrack = null
			this.state.directories[dirId].tracks[trackId].onTop = false
		},
		onTrackClicked({ trackId, dirId }) {
			const track = this.state.directories[dirId].tracks[trackId]
			console.debug('[gpxpod] track clicked', trackId, dirId, 'isEnabled', track.isEnabled)
			if (track.geojson) {
				track.isEnabled = !track.isEnabled
				this.updateTrack(trackId, { isEnabled: track.isEnabled })
			} else {
				console.debug('[gpxpod] no data for', trackId)
				if (this.isPublicPage) {
					this.loadPublicTrack(trackId, true)
				} else {
					this.loadTrack(trackId, dirId, true, true)
				}
			}
		},
		onTrackColorChanged({ trackId, dirId, color }) {
			console.debug('[gpxpod] color change', { trackId, dirId, color })
			this.state.directories[dirId].tracks[trackId].color = color
			this.state.directories[dirId].tracks[trackId].colorCriteria = COLOR_CRITERIAS.none.value
			this.updateTrack(trackId, { color, colorCriteria: COLOR_CRITERIAS.none.value })
		},
		onTrackCriteriaChanged({ trackId, dirId, criteria }) {
			console.debug('[gpxpod] criteria change', { trackId, dirId, criteria })
			this.state.directories[dirId].tracks[trackId].colorCriteria = criteria
			this.updateTrack(trackId, { colorCriteria: criteria })
		},
		onTrackCorrectElevations({ trackId, dirId }) {
			console.debug('[gpxpod] correct elevations', { trackId, dirId })
			this.state.directories[dirId].tracks[trackId].loading = true
			const url = generateUrl('/apps/gpxpod/tracks/{trackId}/elevations', { trackId })
			axios.get(url).then((response) => {
				this.loadDirectory(dirId, true)
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to get corrected elevations')
					+ ': ' + (error.response?.data?.error ?? '')
				)
			}).then(() => {
				this.state.directories[dirId].tracks[trackId].loading = false
			})
		},
		updateTrack(id, values) {
			if (this.state.shareToken) {
				return
			}
			const req = values
			const url = generateUrl('/apps/gpxpod/tracks/{id}', { id })
			axios.put(url, req).then((response) => {
				console.debug('update track', response.data)
			}).catch((error) => {
				console.error(error)
			})
		},
		loadPublicTrack(trackId, enable = false) {
			const dirId = this.state.shareToken
			this.state.directories[dirId].tracks[trackId].loading = true
			const url = generateUrl('/apps/gpxpod/s/{shareToken}/tracks/{trackId}/geojson', { trackId, shareToken: this.state.shareToken })
			const params = {
				params: {
					password: this.state.sharePassword,
				},
			}
			axios.get(url, params).then((response) => {
				this.state.directories[dirId].tracks[trackId].geojson = response.data
				if (enable) {
					this.state.directories[dirId].tracks[trackId].isEnabled = true
				}
				console.debug('[gpxpod] LOAD TRACK response', this.state.directories[dirId].tracks[trackId])
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to load track geojson')
					+ ': ' + (error.response?.data?.error ?? '')
				)
			}).then(() => {
				this.state.directories[dirId].tracks[trackId].loading = false
			})
		},
		loadTrack(trackId, dirId, enable = false, saveEnable = false) {
			this.state.directories[dirId].tracks[trackId].loading = true
			const url = generateUrl('/apps/gpxpod/tracks/{trackId}/geojson', { trackId })
			axios.get(url).then((response) => {
				this.state.directories[dirId].tracks[trackId].geojson = response.data
				if (enable) {
					this.state.directories[dirId].tracks[trackId].isEnabled = true
					if (saveEnable) {
						this.updateTrack(trackId, { isEnabled: true })
					}
				}
				console.debug('[gpxpod] LOAD TRACK response', this.state.directories[dirId].tracks[trackId])
			}).catch((error) => {
				console.error(error)
				showError(
					t('gpxpod', 'Failed to load track geojson')
					+ ': ' + (error.response?.data?.error ?? '')
				)
			}).then(() => {
				this.state.directories[dirId].tracks[trackId].loading = false
			})
		},
		onTrackDetailsClicked({ trackId, dirId }) {
			this.sidebarDirectory = null
			this.sidebarTrack = this.state.directories[dirId].tracks[trackId]
			this.showSidebar = true
			this.activeSidebarTab = 'track-details'
			console.debug('details click', trackId)
		},
		onTrackShareClicked({ trackId, dirId }) {
			this.sidebarDirectory = null
			this.sidebarTrack = this.state.directories[dirId].tracks[trackId]
			this.showSidebar = true
			this.activeSidebarTab = 'track-share'
			console.debug('share click', trackId)
		},
		onDirectoryDetailsClicked(dirId) {
			this.sidebarTrack = null
			this.sidebarDirectory = this.state.directories[dirId]
			this.showSidebar = true
			this.activeSidebarTab = 'directory-details'
			console.debug('details click', dirId)
		},
		onDirectoryShareClicked(dirId) {
			this.sidebarTrack = null
			this.sidebarDirectory = this.state.directories[dirId]
			this.showSidebar = true
			this.activeSidebarTab = 'directory-share'
			console.debug('share click', dirId)
		},
		saveOptions(values) {
			Object.assign(this.state.settings, values)
			// console.debug('[gpxpod] settings saved', this.state.settings)
			if (this.isPublicPage) {
				return
			}
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
		onUpdateActiveTab(tabId) {
			console.debug('active tab change', tabId)
			this.activeSidebarTab = tabId
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
