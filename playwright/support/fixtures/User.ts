/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'

import { createCollective, trashAndDeleteCollective } from './Collective.ts'

export class User {
	constructor(public readonly account: Account) {
	}

	createCollective(options: { name: string, emoji?: string }, page: Page) {
		return createCollective({ ...options, page })
	}

	deleteCollective(options: { id: number }, page: Page) {
		return trashAndDeleteCollective({ ...options, page })
	}
}
