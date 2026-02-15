/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Type declarations for TypeScript (globals, Vite asset imports, and modules).
 */

/// <reference types="vite/client" />

import { translate } from '@nextcloud/l10n'

declare global {
	const t: typeof translate

	const OCA: {
		GpxPod: {
			sharingToken: string
			actionIgnoreLists: string[]
		}
	}
}

declare module '*.svg?raw' {
	const content: string
	export default content
}

export {}
