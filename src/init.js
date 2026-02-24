/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerFileListHeader } from '@nextcloud/files'
import { registerFileListHeaders as legacyRegisterFileListHeader } from '@nextcloud/files-legacy'
import { FilesCollectiveHeader } from './files/FilesCollectiveHeader.ts'

const version = Number.parseInt((window.OC?.config?.version ?? '0').split('.')[0])

if (version >= 33) {
	registerFileListHeader(FilesCollectiveHeader)
} else {
	legacyRegisterFileListHeader(FilesCollectiveHeader)
}
