/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { encodeAttachmentFilename } from './attachmentFilename.ts'

interface Attachment {
	name: string
	type: string
	src: string
}

/**
 * WebDAV URL of a page attachment
 *
 * Attachments are stored next to the page file, so the URL is relative to the
 * page's own WebDAV URL. That URL already points into the user's files or into
 * the share, while attachment paths from the API are relative to the collective.
 *
 * @param pageDavUrl - WebDAV URL of the page file
 * @param attachment - Attachment as listed by the API
 */
export function attachmentDavUrl(pageDavUrl: string, attachment: Attachment): string {
	const pageFolderUrl = pageDavUrl.slice(0, pageDavUrl.lastIndexOf('/'))
	// Folder attachments are files in the page's own folder,
	// text attachments live in its `.attachments.<pageId>` folder (see `src`)
	return attachment.type === 'folder'
		? `${pageFolderUrl}/${encodeAttachmentFilename(attachment.name)}`
		: `${pageFolderUrl}/${attachment.src}`
}
