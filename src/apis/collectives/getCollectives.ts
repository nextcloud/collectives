/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { collectiveIndex } from '../../client'
import { createClient } from '../../client/client/client.gen'

const client = createClient({ axios })
const headers = { 'OCS-APIRequest': true }
const path = Object.freeze({ apiVersion: '1.0' })

/**
 * Get all active (i.e. not trashed) collectives for the current user
 */
export function getCollectives() {
	return collectiveIndex({ client, headers, path })
}
