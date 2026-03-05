/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { webdavUrl } from '../helpers/urls.ts'
import { createCollective, trashAndDeleteCollective } from './Collective.ts'

export class User {
	constructor(public readonly account: Account) {
	}

	async createCollective(options: { name: string, emoji?: string }, page: Page) {
		return await createCollective({ ...options, page })
	}

	async deleteCollective(options: { id: number }, page: Page) {
		return await trashAndDeleteCollective({ ...options, page })
	}

	async uploadFixture(options: { name: string, path: string, mimetype: string }, page: Page) {
		const filepath = resolve(import.meta.dirname, 'files', options.name)
		await page.request.put(
			webdavUrl(this.account.userId, options.path, options.name),
			{
				headers: {
					'Content-Type': options.mimetype,
				},
				data: readFileSync(filepath),
				failOnStatusCode: true,
			},
		)
		const response = await page.request.fetch(
			webdavUrl(this.account.userId, options.path, options.name),
			{
				method: 'PROPFIND',
				headers: {
					'Content-Type': 'text/plain',
					Depth: '0',
				},
				failOnStatusCode: true,
				data: `<?xml version="1.0"?>
	<d:propfind xmlns:d="DAV:" xmlns:nc="http://nextcloud.org/ns" xmlns:oc="http://owncloud.org/ns">
		<d:prop><oc:fileid /></d:prop>
	</d:propfind>`,
			},
		)
		const fileId = (await response.text())
			.match(/<oc:fileid>(\d+)<\/oc:fileid>/)?.[1]
		if (!fileId) {
			throw new Error('File ID not found in response')
		}
		return parseInt(fileId)
	}
}
