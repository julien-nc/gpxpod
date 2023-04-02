import $ from 'jquery'
import { generateUrl } from '@nextcloud/router'

$(function() {

	// if (OCA.Files && OCA.Files.fileActions) {

	function openDirectory(file, data) {
		let dir
		if (data.dir === '/') {
			dir = data.dir + file
		} else {
			dir = data.dir + '/' + file
		}
		const token = $('#sharingToken').val()
		// user is connected
		const url = token
			? generateUrl('apps/gpxpod/publicFolder?token={token}&path={path}', { token, path: dir })
			: generateUrl('apps/gpxpod/old-ui?dir={dir}', { dir })
		window.open(url, '_blank')
	}

	function addDirectoryOpenDirectory(file, data) {
		const token = $('#sharingToken').val()
		// user is not connected
		if (token) {
			openDirectory(file, data)
		}

		let path
		if (data.dir === '/') {
			path = data.dir + file
		} else {
			path = data.dir + '/' + file
		}
		const req = {
			path,
		}
		const url = generateUrl('/apps/gpxpod/directory')
		$.ajax({
			type: 'POST',
			url,
			data: req,
			async: true,
		}).done(function(response) {
			OC.Notification.showTemporary(
				t('gpxpod', 'Directory {p} has been added', { p: path })
			)
		}).fail(function(response) {
			console.debug(t('gpxpod', 'Failed to add directory') + '. ' + response.responseText)
		}).always(function() {
			openDirectory(file, data)
		})
	}

	// file action for directories
	OCA.Files.fileActions.registerAction({
		name: 'viewDirectoryGpxPod',
		displayName: t('gpxpod', 'View in GpxPod'),
		mime: 'httpd/unix-directory',
		permissions: OC.PERMISSION_READ,
		iconClass: 'icon-gpxpod-black',
		actionHandler(file, data) {
			addDirectoryOpenDirectory(file, data)
		},
	})

	function openFile(file, data) {
		const token = $('#sharingToken').val()
		// if we are logged
		const url = token
			? generateUrl('apps/gpxpod/publicFile?token={token}&path={path}&filename={filename}', { token, path: data.dir, filename: file })
			: generateUrl('apps/gpxpod/old-ui?dir={dir}&file={file}', { dir: data.dir, file })
		window.open(url, '_blank')
	}

	function addDirectoryOpenFile(file, data) {
		let path = data.dir
		if (path === '') {
			path = '/'
		}
		const req = {
			path,
		}
		const url = generateUrl('/apps/gpxpod/directory')
		$.ajax({
			type: 'POST',
			url,
			data: req,
			async: true,
		}).done(function(response) {
			OC.Notification.showTemporary(
				t('gpxpod', 'Directory {p} has been added', { p: path })
			)
		}).fail(function(response) {
			// well, no need to tell the user
			// OC.Notification.showTemporary(
			//    t('gpxpod', 'Failed to add directory') + '. ' + response.responseText
			// );
			console.debug(t('gpxpod', 'Failed to add directory') + '. ' + response.responseText)
		}).always(function() {
			openFile(file, data)
		})
	}

	OCA.Files.fileActions.registerAction({
		name: 'viewFileGpxPod',
		displayName: t('gpxpod', 'View in GpxPod'),
		mime: 'application/gpx+xml',
		permissions: OC.PERMISSION_READ,
		iconClass: 'icon-gpxpod-black',
		actionHandler(file, data) {
			addDirectoryOpenFile(file, data)
		},
	})

	// default action is set only for logged in users
	if (!$('#sharingToken').val()) {
		OCA.Files.fileActions.register(
			'application/gpx+xml',
			'viewFileGpxPodDefault',
			OC.PERMISSION_READ,
			'',
			function(file, data) {
				addDirectoryOpenFile(file, data)
			}
		)
		OCA.Files.fileActions.setDefault('application/gpx+xml', 'viewFileGpxPodDefault')
	}
	// }

})
