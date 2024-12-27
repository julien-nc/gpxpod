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
import Vue from 'vue'
import AdminSettings from './components/AdminSettings.vue'
import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

Vue.mixin({ methods: { t, n } })

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('gpxpod', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', async (event) => {
	const View = Vue.extend(AdminSettings)
	new View().$mount('#gpxpod_prefs')
})
