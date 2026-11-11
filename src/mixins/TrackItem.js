/**
 * SPDX-FileCopyrightText: 2015 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { emit } from '@nextcloud/event-bus'
import { generateUrl, getBaseUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import { delay, formatExtensionKey } from '../utils.js'
import { COLOR_CRITERIAS } from '../constants.js'

export default {
	inject: {
		isPublicPage: { default: false },
		shareToken: { default: null },
		shareRootPath: { default: null },
		shareDownloadAllowed: { default: true },
	},

	computed: {
		dotColor() {
			return this.track.colorCriteria === COLOR_CRITERIAS.none.id && this.track.colorExtensionCriteria === ''
				? this.track.color || '#0693e3'
				: 'gradient'
		},
		downloadAllowed() {
			return !(this.isPublicPage && this.shareDownloadAllowed === false)
		},
		downloadLink() {
			const path = this.decodedFolder + '/' + this.decodedTrackName
			if (this.isPublicPage) {
				// public download of a file inside the share, handled by files_sharing
				const sharePath = this.shareRootPath !== null && path.startsWith(this.shareRootPath + '/')
					? path.slice(this.shareRootPath.length)
					: ''
				const pathParam = sharePath !== ''
					? '?path=' + encodeURIComponent(sharePath)
					: ''
				return generateUrl('/s/{token}/download/{filename}', {
					token: this.shareToken,
					filename: this.decodedTrackName,
				}) + pathParam
			}
			return getBaseUrl() + '/remote.php/dav/files/' + getCurrentUser()?.uid + path
		},
		// to make sure it works with tracks created before the vue rewrite (url-encoded values in the marker)
		decodedTrackName() {
			return decodeURIComponent(this.track.name)
		},
		decodedFolder() {
			return decodeURIComponent(this.track.folder)
		},
	},

	methods: {
		getExtensionLabel(ext) {
			return formatExtensionKey(ext)
		},
		onZoomClick() {
			emit('zoom-on-bounds', { north: this.track.north, south: this.track.south, east: this.track.east, west: this.track.west })
		},
		onDetailsClick() {
			emit('track-details-click', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onShareClick() {
			emit('track-share-click', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onCorrectElevationClick() {
			emit('track-correct-elevations', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onHoverIn() {
			emit('track-hover-in', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onHoverOut() {
			emit('track-hover-out', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onMenuColorClick() {
			this.menuOpen = false
			if (this.$refs.colorDot) {
				this.$refs.colorDot.$el.click()
			}
		},
		updateColor(color) {
			delay(() => {
				this.applyUpdateColor(color)
			}, 1000)()
		},
		applyUpdateColor(color) {
			emit('track-color-changed', { trackId: this.track.id, dirId: this.track.directoryId, color })
		},
		onCriteriaChange(criteria) {
			emit('track-criteria-changed', {
				trackId: this.track.id,
				dirId: this.track.directoryId,
				value: {
					criteria,
					extensionCriteria: '',
					extensionCriteriaType: '',
				},
			})
		},
		onColorExtensionCriteriaChange(ext, type) {
			emit('track-criteria-changed', {
				trackId: this.track.id,
				dirId: this.track.directoryId,
				value: {
					extensionCriteria: ext,
					extensionCriteriaType: type,
				},
			})
		},
	},
}
