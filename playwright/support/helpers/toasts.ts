/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

/**
 * Toast from `@nextcloud/dialogs`, located by its role and text
 *
 * Toasts only have hashed CSS module classes. Success, info and warning toasts
 * are `status` regions, error and undo toasts are `alert` regions.
 *
 * @param page - The page showing the toast
 * @param type - Type of the toast
 * @param text - Text the toast contains
 */
export function getToast(page: Page, type: 'success' | 'error', text: string | RegExp) {
	return page.getByRole(type === 'error' ? 'alert' : 'status').filter({ hasText: text })
}
