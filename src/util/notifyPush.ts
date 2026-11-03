/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { listen } from '@nextcloud/notify_push'

/**
 * Listen to page list updates pushed by notify_push
 *
 * The backend only pushes to users with a collective session. Public shares
 * have none, and notify_push can't authenticate anonymous viewers via pre_auth.
 *
 * @param isPublic - Whether the collective is opened via a public share
 * @param handler - Callback for each pushed page list update
 * @return Whether page list updates get pushed
 */
export function listenPageListUpdates(isPublic: boolean, handler: (name: string, body: unknown) => void): boolean {
	if (isPublic) {
		return false
	}
	return listen('collectives_pagelist', handler)
}
