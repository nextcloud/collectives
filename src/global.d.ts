/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/// <reference types="@nextcloud/typings" />

import type { App } from 'vue'

declare global {
	interface Window {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		OCA: Record<string, any>
		app: App
	}
}

export {}
