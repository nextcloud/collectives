/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getClient } from '@nextcloud/files/dav'

// init webdav client
const client = getClient()
export default client
