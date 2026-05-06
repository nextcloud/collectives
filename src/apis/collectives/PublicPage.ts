/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PublicPageCreateData, PublicPageMoveOrCopyData } from '../../client/types.gen.js'

import { publicPageAddTag, publicPageContentSearch, publicPageCreate, publicPageDeleteAttachment, publicPageGet, publicPageGetAttachments, publicPageIndex, publicPageMoveOrCopy, publicPageRemoveTag, publicPageRenameAttachment, publicPageRestoreAttachment, publicPageSetEmoji, publicPageSetFullWidth, publicPageSetSubpageOrder, publicPageTouch, publicPageTrash, publicPageTrashDelete, publicPageTrashIndex, publicPageTrashRestore, publicPageUploadAttachment } from '../../client/sdk.gen.js'
import { Client } from './Client.js'

type Identifier = { token: string }

export default class PublicPage extends Client<Identifier> {
	/**
	 * Get all pages in the PublicPage context - i.e. public share.
	 *
	 */
	getPages() {
		return publicPageIndex(this.options())
	}

	/**
	 * Get all trashed pages in the public share.
	 *
	 */
	getTrashPages() {
		return publicPageTrashIndex(this.options())
	}

	/**
	 * Create a new page in the public share.
	 *
	 * @param page - properties of the new page
	 */
	createPage(page: PublicPageCreateData['body'] & { parentId: number }) {
		return publicPageCreate(this.options({ parentId: page.parentId }, page))
	}

	/**
	 * Get a page in the given context (collective or public share)
	 *
	 * @param pageId - ID of the page to retrieve
	 */
	getPage(pageId: number) {
		return publicPageGet(this.options({ id: pageId }))
	}

	/**
	 * Touch a page in the given context (collective or public share)
	 *
	 * @param pageId - ID of the page to touch
	 */
	touchPage(pageId: number) {
		return publicPageTouch(this.options({ id: pageId }))
	}

	/**
	 * Send a request to the move or copy endpoint with the given body
	 *
	 * @param pageId - ID of the page in question
	 * @param body - request body to send
	 */
	#moveOrCopyPage(pageId: number, body: PublicPageMoveOrCopyData['body']) {
		return publicPageMoveOrCopy(this.options({ id: pageId }, body))
	}

	/**
	 * Rename a page in the given context (collective or public share)
	 *
	 * @param pageId - ID of the page to rename
	 * @param title - New title for the page
	 */
	renamePage(pageId: number, title: string) {
		return this.#moveOrCopyPage(pageId, { title })
	}

	/**
	 * Copy a page inside the given context (collective or public share)
	 *
	 * @param pageId - ID of the page to copy
	 * @param parentId - ID of the page to copy to
	 * @param index - Index for subpage order of parent page
	 */
	copyPage(pageId: number, parentId: number, index: number) {
		return this.#moveOrCopyPage(pageId, { parentId, index, copy: true })
	}

	/**
	 * Move a page inside the given context (collective or public share)
	 *
	 * @param pageId - ID of the page to move
	 * @param parentId - ID of the page to move to
	 * @param index - Index for subpage order of parent page
	 */
	movePage(pageId: number, parentId: number, index: number) {
		return this.#moveOrCopyPage(pageId, { parentId, index })
	}

	/**
	 * Set emoji for a page
	 *
	 * @param pageId - ID of the page to update
	 * @param emoji - New emojie for the page
	 */
	setPageEmoji(pageId: number, emoji: string) {
		return publicPageSetEmoji(this.options({ id: pageId }, { emoji }))
	}

	/**
	 * Set full width for a page
	 *
	 * @param pageId - ID of the page to update
	 * @param fullWidth - Full width for the page
	 */
	setFullWidth(pageId: number, fullWidth: boolean) {
		return publicPageSetFullWidth(this.options({ id: pageId }, { fullWidth }))
	}

	/**
	 * Set subpageOrder for a page
	 *
	 * @param pageId - ID of the page to update
	 * @param subpageOrder - New subpageOrdere for the page
	 */
	setPageSubpageOrder(pageId: number, subpageOrder: string) {
		return publicPageSetSubpageOrder(this.options({ id: pageId }, { subpageOrder }))
	}

	/**
	 * Add tag to a page
	 *
	 * @param pageId - ID of the page to update
	 * @param tagId - ID of the tag to add
	 */
	addPageTag(pageId: number, tagId: number) {
		return publicPageAddTag(this.options({ id: pageId, tagId }))
	}

	/**
	 * Remove tag from a page
	 *
	 * @param pageId - ID of the page to update
	 * @param tagId - ID of the tag to remove
	 */
	removePageTag(pageId: number, tagId: number) {
		return publicPageRemoveTag(this.options({ id: pageId, tagId }))
	}

	/**
	 * Trash a page in the given context (collective or public share)
	 *
	 * @param pageId - ID of the page to trash
	 */
	trashPage(pageId: number) {
		return publicPageTrash(this.options({ id: pageId }))
	}

	/**
	 * Restore the page with the given id from trash
	 *
	 * @param pageId - ID of the page to restore
	 */
	restorePage(pageId: number) {
		return publicPageTrashRestore(this.options({ id: pageId }))
	}

	/**
	 * Delete the page with the given id from trash
	 *
	 * @param pageId - ID of the page to trash
	 */
	deletePage(pageId: number) {
		return publicPageTrashDelete(this.options({ id: pageId }))
	}

	/**
	 * Get list of attachments for a page
	 *
	 * @param pageId - ID of the page to list attachments for
	 */
	getPageAttachments(pageId: number) {
		return publicPageGetAttachments(this.options({ id: pageId }))
	}

	/**
	 * Upload attachment of a page
	 *
	 * @param pageId - ID of the page that the attachment belongs to
	 * @param formData - The formData containing the attachment
	 */
	uploadAttachment(pageId: number, formData: FormData) {
		return publicPageUploadAttachment({
			...this.options({ id: pageId }, formData),
			headers: {
				...headers,
				'Content-Type': 'multipart/form-data',
			},
		})
	}

	/**
	 * Rename attachment of a page
	 *
	 * @param pageId - ID of the page that the attachment belongs to
	 * @param attachmentId - ID of the attachment to rename
	 * @param name - Target name of the attachment
	 */
	renameAttachment(pageId: number, attachmentId: number, name: string) {
		return publicPageRenameAttachment(this.options({ id: pageId, attachmentId }, { name }))
	}

	/**
	 * Delete attachment of a page
	 *
	 * @param pageId - ID of the page that the attachment belongs to
	 * @param attachmentId - ID of the attachment to delete
	 */
	deleteAttachment(pageId: number, attachmentId: number) {
		return publicPageDeleteAttachment(this.options({ id: pageId, attachmentId }))
	}

	/**
	 * Restore attachment of a page from trash
	 *
	 * @param pageId - ID of the page that the attachment belongs to
	 * @param attachmentId - ID of the attachment to restore
	 */
	restoreAttachment(pageId: number, attachmentId: number) {
		return publicPageRestoreAttachment(this.options({ id: pageId, attachmentId }))
	}

	/**
	 * Perform index search on pages in given collective
	 *
	 * @param searchString string to search for
	 */
	contentSearch(searchString: string) {
		return publicPageContentSearch({
			...this.options(),
			query: { searchString },
		})
	}
}
