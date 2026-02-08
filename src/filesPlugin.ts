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
import { registerFileAction, Permission, FileType, DefaultType } from '@nextcloud/files'
import type { IFileAction } from '@nextcloud/files'
import GpxPodIcon from '../img/app_black.svg?raw'

const state = loadState('gpxpod', 'gpxpod-files', {})
if (!OCA.GpxPod) {
	/**
	 * @namespace
	 */
	OCA.GpxPod = {
		sharingToken: state.sharingToken,
		actionIgnoreLists: [
			'trashbin',
			// 'files.public',
		],
	}
}

const openDirectory = (path) => {
	const url = OCA.GpxPod.sharingToken
		? generateUrl('apps/gpxpod/s/{sharingToken}?path={path}', { sharingToken: OCA.GpxPod.sharingToken, path })
		: generateUrl('apps/gpxpod/?dir={path}', { path })
	window.open(url, '_blank')
}

const addDirectoryOpenDirectory = (path) => {
	// user is not connected
	if (OCA.GpxPod.sharingToken) {
		openDirectory(path)
		return
	}

	const req = {
		path,
	}
	const url = generateUrl('/apps/gpxpod/directories')
	axios.post(url, req).then(() => {
		console.debug(t('gpxpod', 'Directory {p} has been added', { p: path }))
	}).catch((error) => {
		console.debug(t('gpxpod', 'Failed to add directory'), error)
	}).then(() => {
		openDirectory(path)
	})
}

const openFile = (path, fileName, dir) => {
	// if we are logged in
	const url = OCA.GpxPod.sharingToken
		? generateUrl('apps/gpxpod/s/{sharingToken}?path={path}', {
			sharingToken: OCA.GpxPod.sharingToken,
			path,
		})
		: generateUrl('apps/gpxpod/?dir={dir}&file={fileName}', { dir, fileName })
	window.open(url, '_blank')
}

const addDirectoryOpenFile = (path, fileName, dir) => {
	// user is not connected
	if (OCA.GpxPod.sharingToken) {
		openFile(path, fileName, dir)
		return
	}

	const dirPath = dir === ''
		? '/'
		: dir
	const req = {
		path: dirPath,
	}
	const url = generateUrl('/apps/gpxpod/directories')
	axios.post(url, req).then(() => {
		console.debug(t('gpxpod', 'Directory {p} has been added', { p: dirPath }))
	}).catch((error) => {
		console.debug(t('gpxpod', 'Failed to add directory'), error)
	}).then(() => {
		openFile(path, fileName, dir)
	})
}

const compare = (files) => {
	let i = 1
	const params = {}
	files.forEach((f) => {
		params['path' + i] = f.path
		i++
	})
	const urlParams = new URLSearchParams(params)
	const url = generateUrl('apps/gpxpod/compare?') + urlParams.toString()
	window.open(url, '_blank')
}

const viewDirectoryAction: IFileAction = {
	id: 'viewDirectoryGpxPod',
	displayName: () => t('gpxpod', 'View in GpxPod'),
	enabled({ nodes, view }) {
		return !OCA.GpxPod.actionIgnoreLists.includes(view.id)
			&& nodes.length > 0
			&& nodes.every(({ permissions }) => (permissions & Permission.READ) !== 0)
			&& nodes.every(({ type }) => type === FileType.Folder)
	},
	iconSvgInline: () => GpxPodIcon,
	async exec({ nodes }) {
		addDirectoryOpenDirectory(nodes[0].path)
		return null
	},
}
registerFileAction(viewDirectoryAction)

const viewFileAction: IFileAction = {
	id: 'viewFileGpxPod',
	displayName: () => t('gpxpod', 'View in GpxPod'),
	enabled({ nodes, view }) {
		return !OCA.GpxPod.actionIgnoreLists.includes(view.id)
			&& nodes.length > 0
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& !nodes.some(({ type }) => type !== FileType.File)
			&& !nodes.some(({ mime }) => mime !== 'application/gpx+xml')
	},
	iconSvgInline: () => GpxPodIcon,
	async exec({ nodes }) {
		const node = nodes[0]
		addDirectoryOpenFile(node.path, node.basename, node.dirname)
		return true
	},
	default: OCA.GpxPod.sharingToken ? null : DefaultType.DEFAULT,
}
registerFileAction(viewFileAction)

const compareAction: IFileAction = {
	id: 'gpxpodCompare',
	displayName: () => t('gpxpod', 'Compare with GpxPod'),
	order: -2,
	enabled({ nodes, view }) {
		// we don't want 'files.public' or any other view
		return view.id === 'files'
			&& nodes.length > 1
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& !nodes.some(({ type }) => type !== FileType.File)
			&& !nodes.some(({ mime }) => mime !== 'application/gpx+xml')
	},
	iconSvgInline: () => GpxPodIcon,
	async exec() { return null },
	async execBatch({ nodes }) {
		compare(nodes)
		return nodes.map(() => null)
	},
}
registerFileAction(compareAction)
