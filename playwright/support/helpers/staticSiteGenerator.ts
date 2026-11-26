/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Browser, Page } from '@playwright/test'

import { loginAsUser } from '../fixtures/random-user.ts'
import { getMockStaticSiteGeneratorUrl, STATIC_SITE_GENERATOR_TOKEN } from '../staticSiteGenerator.js'
import { ocsHeaders } from './urls.ts'

const ADMIN = { userId: 'admin', password: 'admin', language: 'en-US' }

/**
 * Points Collectives at the mock static site generator (see staticSiteGenerator.js), through the
 * same provisioning_api endpoint the admin settings UI uses to save these values.
 *
 * This matters because Nextcloud caches app config in APCu: writing it via `occ` (a separate CLI
 * process) doesn't invalidate the copy Apache/FPM holds, so the real publish request would still
 * see it as unconfigured. Going through HTTP instead makes Apache/FPM itself the writer.
 *
 * Call this again right before any test that needs a working generator: admin-settings.spec.ts
 * deliberately sets a broken URL/token to test the "not reachable" UI, and all spec files share
 * the same container within a test run.
 *
 * @param browser - the browser to log in as admin with
 * @param baseURL - base URL of the Nextcloud instance
 */
export async function pointAtMockStaticSiteGenerator(browser: Browser, baseURL: string | undefined): Promise<void> {
	const page = await loginAsUser(browser, baseURL, ADMIN)
	try {
		const generatorUrl = await getMockStaticSiteGeneratorUrl()
		await setAppConfig(page, 'publish_enabled', 'true')
		await setAppConfig(page, 'static_site_generator_url', generatorUrl)
		await setAppConfig(page, 'static_site_generator_token', STATIC_SITE_GENERATOR_TOKEN)
	} finally {
		await page.close()
	}
}

/**
 * @param page - an authenticated admin page (see loginAsUser)
 * @param key - app config key
 * @param value - value to set
 */
async function setAppConfig(page: Page, key: string, value: string): Promise<void> {
	const response = await page.request.post(
		`/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/collectives/${key}`,
		{ headers: ocsHeaders, data: { value } },
	)
	if (!response.ok()) {
		throw new Error(`Failed to set collectives app config "${key}": ${response.status()} ${await response.text()}`)
	}
}
