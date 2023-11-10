import Vue from 'vue'
import App from './App.vue'
import './bootstrap.js'
import '../css/maplibre.scss'
import '@nextcloud/dialogs/style.css'
import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

import VueClipboard from 'vue-clipboard2'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
Vue.directive('tooltip', Tooltip)
Vue.use(VueClipboard)

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = generateFilePath('gpxpod', '', 'js/') // eslint-disable-line

const View = Vue.extend(App)
new View().$mount('#content')
