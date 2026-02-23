/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type User as Account } from '@nextcloud/e2e-test-server'
import { type Page } from '@playwright/test'
import { createCollective, trashAndDeleteCollective } from './Collective.ts'

export class User {
	constructor(
		public readonly account: Account,
		public readonly page: Page,
	) {
	}

	get request() {
		return this.page.request
	}

	createCollective(options: { name: string, emoji?: string }) {
		return createCollective({ ...options, user: this })
	}

	deleteCollective(options: { id: number }) {
		return trashAndDeleteCollective({ ...options, user: this })
	}
}
