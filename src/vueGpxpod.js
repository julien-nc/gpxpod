import Vue from 'vue'
import App from './App.vue'
import '../css/handwritten/maplibre.scss'
import '@nextcloud/dialogs/style.css'

import VueClipboard from 'vue-clipboard2'
Vue.use(VueClipboard)
Vue.mixin({ methods: { t, n } })

document.addEventListener('DOMContentLoaded', async (event) => {
	const View = Vue.extend(App)
	new View().$mount('#content')
	console.debug('--------------------- mounted gpxpod')
})
