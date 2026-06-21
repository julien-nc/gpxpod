/**
 * SPDX-FileCopyrightText: 2015 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// import { linkTo } from '@nextcloud/router'
// import { getRequestToken } from '@nextcloud/auth'

// __webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
// __webpack_public_path__ = linkTo('gpxpod', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', async (event) => {
	const { createApp } = await import('vue')
	const { default: AdminSettings } = await import('./components/AdminSettings.vue')

	const app = createApp(AdminSettings)
	app.mixin({ methods: { t, n } })
	app.mount('#gpxpod_prefs')
})
