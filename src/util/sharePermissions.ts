/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Collective } from '../types.ts'

import { memberLevels } from '../constants.js'

/**
 * Share links can only be editable if all collective members can edit
 *
 * @param collective The collective
 */
export function sharesCanBeEditable(collective: Pick<Collective, 'editPermissionLevel'>): boolean {
	return collective.editPermissionLevel <= memberLevels.LEVEL_MEMBER
}
