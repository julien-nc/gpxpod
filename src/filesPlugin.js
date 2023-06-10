/**
 * Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'

(function() {
	const state = loadState('gpxpod', 'gpxpod-files', {})
	if (!OCA.GpxPod) {
		/**
		 * @namespace
		 */
		OCA.GpxPod = {
			sharingToken: state.sharingToken,
		}
	}

	/**
	 * @namespace
	 */
	OCA.GpxPod.FilesPlugin = {
		ignoreLists: [
			'trashbin',
			// 'files.public',
		],

		attach(fileList) {
			if (this.ignoreLists.indexOf(fileList.id) >= 0) {
				return
			}

			if (fileList.id !== 'files.public') {
				fileList.registerMultiSelectFileAction({
					name: 'gpxpodCompare',
					displayName: t('gpxpod', 'Compare with GpxPod'),
					iconClass: 'icon-gpxpod-black',
					order: -2,
					action: (selectedFiles) => {
						this.compare(selectedFiles, fileList)
					},
				})

				// when the multiselect menu is opened =>
				// only show compare action if only gpx files are selected
				fileList.$el.find('.actions-selected').click(() => {
					if (Object.keys(fileList._selectedFiles).length === 1) {
						fileList.fileMultiSelectMenu.toggleItemVisibility('gpxpodCompare', false)
						return
					}

					let showCompareAction = true
					for (const fid in fileList._selectedFiles) {
						const file = fileList.files.find((t) => parseInt(fid) === t.id)
						if (file && file.mimetype !== 'application/gpx+xml') {
							showCompareAction = false
							break
						}
					}
					fileList.fileMultiSelectMenu.toggleItemVisibility('gpxpodCompare', showCompareAction)
				})
			}

			fileList.fileActions.registerAction({
				name: 'viewDirectoryGpxPod',
				displayName: t('gpxpod', 'View in GpxPod'),
				mime: 'httpd/unix-directory',
				order: -139,
				permissions: OC.PERMISSION_READ,
				iconClass: 'icon-gpxpod-black',
				actionHandler: (fileName, context) => {
					this.addDirectoryOpenDirectory(fileName, context, this)
				},
			})

			fileList.fileActions.registerAction({
				name: 'viewFileGpxPod',
				displayName: t('gpxpod', 'View in GpxPod'),
				mime: 'application/gpx+xml',
				order: -139,
				permissions: OC.PERMISSION_READ,
				iconClass: 'icon-gpxpod-black',
				actionHandler: (fileName, context) => {
					this.addDirectoryOpenFile(fileName, context, this)
				},
			})

			// default action is set only for logged in users
			if (fileList.id !== 'files.public') {
				if (!OCA.GpxPod.sharingToken) {
					fileList.fileActions.register(
						'application/gpx+xml',
						'viewFileGpxPodDefault',
						OC.PERMISSION_READ,
						'',
						(fileName, context) => {
							this.addDirectoryOpenFile(fileName, context, this)
						}
					)
					fileList.fileActions.setDefault('application/gpx+xml', 'viewFileGpxPodDefault')
				}
			}

		},

		compare: (selectedFiles, fileList) => {
			let i = 1
			const params = {}
			selectedFiles.forEach((f) => {
				params['path' + i] = f.path === '/'
					? '/' + f.name
					: f.path + '/' + f.name
				i++
			})
			const urlParams = new URLSearchParams(params)
			const url = generateUrl('apps/gpxpod/compare?') + urlParams.toString()
			window.open(url, '_blank')
		},

		openDirectory: (file, data) => {
			const dir = data.dir === '/'
				? data.dir + file
				: data.dir + '/' + file
			const url = OCA.GpxPod.sharingToken
				? generateUrl('apps/gpxpod/s/{sharingToken}?path={path}', { sharingToken: OCA.GpxPod.sharingToken, path: dir })
				: generateUrl('apps/gpxpod/?dir={dir}', { dir })
			window.open(url, '_blank')
		},

		addDirectoryOpenDirectory: (fileName, context, that) => {
			// user is not connected
			if (OCA.GpxPod.sharingToken) {
				that.openDirectory(fileName, context)
				return
			}

			const path = context.dir === '/'
				? context.dir + fileName
				: context.dir + '/' + fileName
			const req = {
				path,
			}
			const url = generateUrl('/apps/gpxpod/directories')
			axios.post(url, req).then((response) => {
				console.debug(t('gpxpod', 'Directory {p} has been added', { p: path }))
			}).catch((error) => {
				console.debug(t('gpxpod', 'Failed to add directory'), error)
			}).then(() => {
				that.openDirectory(fileName, context)
			})
		},

		openFile: (fileName, context) => {
			// if we are logged in
			const url = OCA.GpxPod.sharingToken
				? generateUrl('apps/gpxpod/s/{sharingToken}?path={path}', {
					sharingToken: OCA.GpxPod.sharingToken,
					path: context.dir + '/' + fileName,
				})
				: generateUrl('apps/gpxpod/?dir={dir}&file={fileName}', { dir: context.dir, fileName })
			window.open(url, '_blank')
		},

		addDirectoryOpenFile: (fileName, context, that) => {
			// user is not connected
			if (OCA.GpxPod.sharingToken) {
				that.openFile(fileName, context)
				return
			}

			const path = context.dir === ''
				? '/'
				: context.dir
			const req = {
				path,
			}
			const url = generateUrl('/apps/gpxpod/directories')
			axios.post(url, req).then((response) => {
				console.debug(t('gpxpod', 'Directory {p} has been added', { p: path }))
			}).catch((error) => {
				console.debug(t('gpxpod', 'Failed to add directory'), error)
			}).then(() => {
				that.openFile(fileName, context)
			})
		},
	}

})()

OC.Plugins.register('OCA.Files.FileList', OCA.GpxPod.FilesPlugin)
