import Vue from 'vue'
import ComparisonContent from './ComparisonContent.vue'
import './bootstrap.js'
import '../css/maplibre.scss'
import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = generateFilePath('gpxpod', '', 'js/') // eslint-disable-line

const View = Vue.extend(ComparisonContent)
new View().$mount('#content')
