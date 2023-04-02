import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'

const state = loadState('gpxpod', 'gpxpod-files', {})
const sharingToken = state.sharingToken

function openDirectory(file, data) {
	const dir = data.dir === '/'
		? data.dir + file
		: data.dir + '/' + file
	const url = sharingToken
		? generateUrl('apps/gpxpod/publicFolder?token={sharingToken}&path={path}', { sharingToken, path: dir })
		: generateUrl('apps/gpxpod/old-ui?dir={dir}', { dir })
	window.open(url, '_blank')
}

function addDirectoryOpenDirectory(file, data) {
	// user is not connected
	if (sharingToken) {
		openDirectory(file, data)
		return
	}

	const path = data.dir === '/'
		? data.dir + file
		: data.dir + '/' + file
	const req = {
		path,
	}
	const url = generateUrl('/apps/gpxpod/directories')
	axios.post(url, req).then((response) => {
		console.debug(t('gpxpod', 'Directory {p} has been added', { p: path }))
	}).catch((error) => {
		console.debug(t('gpxpod', 'Failed to add directory'), error)
	}).then(() => {
		openDirectory(file, data)
	})
}

function openFile(file, data) {
	// if we are logged
	const url = sharingToken
		? generateUrl('apps/gpxpod/publicFile?token={sharingToken}&path={path}&filename={filename}', { sharingToken, path: data.dir, filename: file })
		: generateUrl('apps/gpxpod/old-ui?dir={dir}&file={file}', { dir: data.dir, file })
	window.open(url, '_blank')
}

function addDirectoryOpenFile(file, data) {
	// user is not connected
	if (sharingToken) {
		openFile(file, data)
		return
	}

	const path = data.dir === ''
		? '/'
		: data.dir
	const req = {
		path,
	}
	const url = generateUrl('/apps/gpxpod/directories')
	axios.post(url, req).then((response) => {
		console.debug(t('gpxpod', 'Directory {p} has been added', { p: path }))
	}).catch((error) => {
		console.debug(t('gpxpod', 'Failed to add directory'), error)
	}).then(() => {
		openFile(file, data)
	})
}

document.addEventListener('DOMContentLoaded', () => {
	if (OCA.Files && OCA.Files.fileActions) {
		// file action for directories
		OCA.Files.fileActions.registerAction({
			name: 'viewDirectoryGpxPod',
			displayName: t('gpxpod', 'View in GpxPod'),
			mime: 'httpd/unix-directory',
			permissions: OC.PERMISSION_READ,
			iconClass: 'icon-gpxpod-black',
			actionHandler: (file, data) => {
				addDirectoryOpenDirectory(file, data)
			},
		})

		OCA.Files.fileActions.registerAction({
			name: 'viewFileGpxPod',
			displayName: t('gpxpod', 'View in GpxPod'),
			mime: 'application/gpx+xml',
			permissions: OC.PERMISSION_READ,
			iconClass: 'icon-gpxpod-black',
			actionHandler: (file, data) => {
				addDirectoryOpenFile(file, data)
			},
		})

		// default action is set only for logged in users
		if (!sharingToken) {
			OCA.Files.fileActions.register(
				'application/gpx+xml',
				'viewFileGpxPodDefault',
				OC.PERMISSION_READ,
				'',
				(file, data) => {
					addDirectoryOpenFile(file, data)
				}
			)
			OCA.Files.fileActions.setDefault('application/gpx+xml', 'viewFileGpxPodDefault')
		}
	}
})
