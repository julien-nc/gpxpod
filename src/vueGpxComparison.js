import Vue from 'vue'
import ComparisonContent from './ComparisonContent.vue'
import '../css/maplibre.scss'

Vue.mixin({ methods: { t, n } })

const View = Vue.extend(ComparisonContent)
new View().$mount('#content')
