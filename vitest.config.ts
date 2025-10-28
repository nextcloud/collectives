/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig } from 'vitest/config'

export default defineConfig({
	test: {
		include: ['src/tests/*.spec.[jt]s', 'src/tests/**/*.spec.[jt]s'],
	},
})
