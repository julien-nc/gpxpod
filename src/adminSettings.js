/**
 * Nextcloud - Gpxpod
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('gpxpod', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', async (event) => {
	const { default: Vue } = await import(/* webpackChunkName: "admin-settings-lazy" */'vue')
	Vue.mixin({ methods: { t, n } })
	const { default: AdminSettings } = await import(/* webpackChunkName: "admin-settings-lazy" */'./components/AdminSettings.vue')
	const View = Vue.extend(AdminSettings)
	new View().$mount('#gpxpod_prefs')
})
