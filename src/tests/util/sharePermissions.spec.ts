/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { memberLevels } from '../../constants.js'
import { sharesCanBeEditable } from '../../util/sharePermissions.ts'

describe('sharesCanBeEditable', () => {
	it.each([
		['members', memberLevels.LEVEL_MEMBER, true],
		['moderators', memberLevels.LEVEL_MODERATOR, false],
		['admins', memberLevels.LEVEL_ADMIN, false],
	])('editing limited to %s', (_, editPermissionLevel, expected) => {
		expect(sharesCanBeEditable({ editPermissionLevel })).toBe(expected)
	})
})
