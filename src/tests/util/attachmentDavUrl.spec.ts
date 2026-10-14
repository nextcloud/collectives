/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { attachmentDavUrl } from '../../util/attachmentDavUrl.ts'

const textAttachment = { name: 'my image.png', type: 'text', src: '.attachments.42/my%20image.png' }

describe('attachmentDavUrl', () => {
	it.each([
		['user files', 'https://cloud.example/remote.php/dav/files/alice/Collectives/Garden/Parent/Page.md', 'https://cloud.example/remote.php/dav/files/alice/Collectives/Garden/Parent'],
		['collective share', 'https://cloud.example/public.php/dav/files/token/Parent/Page.md', 'https://cloud.example/public.php/dav/files/token/Parent'],
		['page share', 'https://cloud.example/public.php/dav/files/token/Readme.md', 'https://cloud.example/public.php/dav/files/token'],
	])('locates text attachments next to the page in %s', (_, pageDavUrl, pageFolderUrl) => {
		expect(attachmentDavUrl(pageDavUrl, textAttachment))
			.toBe(`${pageFolderUrl}/.attachments.42/my%20image.png`)
	})

	it('locates folder attachments in the page folder', () => {
		const attachment = { name: 'notes (1).pdf', type: 'folder', src: 'Page/notes%20%281%29.pdf' }
		expect(attachmentDavUrl('https://cloud.example/remote.php/dav/files/alice/Collectives/Garden/Page/Readme.md', attachment))
			.toBe('https://cloud.example/remote.php/dav/files/alice/Collectives/Garden/Page/notes%20%281%29.pdf')
	})
})
