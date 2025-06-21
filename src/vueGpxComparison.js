import { createApp } from 'vue'
import ComparisonContent from './ComparisonContent.vue'
import '../css/maplibre.scss'
import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = generateFilePath('gpxpod', '', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', async (event) => {
	const app = createApp(ComparisonContent)
	app.mixin({ methods: { t, n } })
	app.mount('#content')
})
