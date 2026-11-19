/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import { isValidStaticSiteGeneratorUrl } from '../../util/staticSiteGeneratorUrl.js'

describe('isValidStaticSiteGeneratorUrl', () => {
	test.each([
		'',
		'https://ssg.example.org',
		'https://ssg.example.org/',
		'http://localhost:8080/ssg',
	])('accepts %j', (url) => {
		expect(isValidStaticSiteGeneratorUrl(url)).toBe(true)
	})

	test.each([
		'ssg.example.org',
		'ftp://ssg.example.org',
		'https://',
		'https://ssg example.org',
		' https://ssg.example.org',
	])('rejects %j', (url) => {
		expect(isValidStaticSiteGeneratorUrl(url)).toBe(false)
	})
})
