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

document.addEventListener('DOMContentLoaded', async (event) => {
	const { default: Vue } = await import('vue')
	Vue.mixin({ methods: { t, n } })
	const { default: AdminSettings } = await import('./components/AdminSettings.vue')
	const View = Vue.extend(AdminSettings)
	new View().$mount('#gpxpod_prefs')
})
