/**
 * Nextcloud - Gpxpod
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

import Vue from 'vue'
import './bootstrap'
import AdminSettings from './components/AdminSettings'

const View = Vue.extend(AdminSettings)
new View().$mount('#gpxpod_prefs')
