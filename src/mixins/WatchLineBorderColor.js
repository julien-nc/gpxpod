/**
 * SPDX-FileCopyrightText: 2015 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export default {
	watch: {
		borderColor(newVal) {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-color', newVal)
			}
		},
	},
}
