/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'
import eslint from 'vite-plugin-eslint'
import stylelint from 'vite-plugin-stylelint'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
	vueGpxpod: 'src/vueGpxpod.js',
	vueGpxComparison: 'src/vueGpxComparison.js',
	filesPlugin: 'src/filesPlugin.js',
	adminSettings: 'src/adminSettings.js',
	// gpxpod: 'src/gpxpod.js',
	// gpxvcomp: 'src/gpxvcomp.js'
}, {
	config: {
		css: {
			modules: {
				localsConvention: 'camelCase',
			},
			preprocessorOptions: {
				scss: {
					api: 'modern-compiler',
				},
			},
		},
		plugins: [eslint(), stylelint()],
	},
	inlineCSS: { relativeCSSInjection: true },
	minify: isProduction,
})
