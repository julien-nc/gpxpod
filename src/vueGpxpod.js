import Vue from 'vue'
import App from './App.vue'
import '../css/maplibre.scss'
import '@nextcloud/dialogs/style.css'

import VueClipboard from 'vue-clipboard2'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
Vue.directive('tooltip', Tooltip)
Vue.use(VueClipboard)
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)
new View().$mount('#content')
