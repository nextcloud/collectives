/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PageCreateData, PageMoveOrCopyData } from '../../client/types.gen.js'

import { pageAddTag, pageContentSearch, pageCreate, pageDeleteAttachment, pageGet, pageGetAttachments, pageIndex, pageMoveOrCopy, pageMoveOrCopyToCollective, pageRemoveTag, pageRenameAttachment, pageRestoreAttachment, pageSetEmoji, pageSetFullWidth, pageSetSubpageOrder, pageTouch, pageTrash, pageTrashDelete, pageTrashIndex, pageTrashRestore, pageUploadAttachment } from '../../client/sdk.gen.js'
import { Client } from './Client.js'

type Identifier = { collectiveId: number }

export default class Page extends Client<Identifier> {
	/**
	 * Get all pages in the given context (collective or public share)
	 *
	 */
	getPages() {
		return pageIndex(this.options())
	}

	/**
	 * Get all trashed pages in the given context.
	 *
	 */
	getTrashPages() {
		return pageTrashIndex(this.options())
	}

	/**
	 * Create a new page in the given context (collective or public share)
	 *
	 * @param page - properties of the new page
	 */
	createPage(page: PageCreateData['body'] & { parentId: number }) {
		return pageCreate(this.options({ parentId: page.parentId }, page))
	}

	/**
	 * Get a page in the given context (collective or public share)
	 *
	 * @param pageId - ID of the page to retrieve
	 */
	getPage(pageId: number) {
		return pageGet(this.options({ id: pageId }))
	}

	/**
	 * Touch a page in the given context (collective or public share)
	 *
	 * @param pageId - ID of the page to touch
	 */
	touchPage(pageId: number) {
		return pageTouch(this.options({ id: pageId }))
	}

	/**
	 * Send a request to the move or copy endpoint with the given body
	 *
	 * @param pageId - ID of the page in question
	 * @param body - request body to send
	 */
	#moveOrCopyPage(pageId: number, body: PageMoveOrCopyData['body']) {
		return pageMoveOrCopy(this.options({ id: pageId }, body))
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
	 * Copy page to another collective
	 *
	 * @param pageId - ID of the page to move
	 * @param newCollectiveId - ID of the new collective
	 * @param parentId - ID of the page to move to
	 * @param index - Index for subpage order of parent page
	 */
	copyPageToCollective(pageId: number, newCollectiveId: number, parentId: number, index: number) {
		return pageMoveOrCopyToCollective(this.options(
			{ id: pageId, newCollectiveId },
			{ parentId, index, copy: true },
		))
	}

	/**
	 * Move page to another collective
	 *
	 * @param pageId - ID of the page to move
	 * @param newCollectiveId - ID of the new collective
	 * @param parentId - ID of the page to move to
	 * @param index - Index for subpage order of parent page
	 */
	movePageToCollective(pageId: number, newCollectiveId: number, parentId: number, index: number) {
		return pageMoveOrCopyToCollective(this.options(
			{ id: pageId, newCollectiveId },
			{ parentId, index },
		))
	}

	/**
	 * Set emoji for a page
	 *
	 * @param pageId - ID of the page to update
	 * @param emoji - New emojie for the page
	 */
	setPageEmoji(pageId: number, emoji: string) {
		return pageSetEmoji(this.options({ id: pageId }, { emoji }))
	}

	/**
	 * Set full width for a page
	 *
	 * @param pageId - ID of the page to update
	 * @param fullWidth - Full width for the page
	 */
	setFullWidth(pageId: number, fullWidth: boolean) {
		return pageSetFullWidth(this.options({ id: pageId }, { fullWidth }))
	}

	/**
	 * Set subpageOrder for a page
	 *
	 * @param pageId - ID of the page to update
	 * @param subpageOrder - New subpageOrdere for the page
	 */
	setPageSubpageOrder(pageId: number, subpageOrder: string) {
		return pageSetSubpageOrder(this.options({ id: pageId }, { subpageOrder }))
	}

	/**
	 * Add tag to a page
	 *
	 * @param pageId - ID of the page to update
	 * @param tagId - ID of the tag to add
	 */
	addPageTag(pageId: number, tagId: number) {
		return pageAddTag(this.options({ id: pageId, tagId }))
	}

	/**
	 * Remove tag from a page
	 *
	 * @param pageId - ID of the page to update
	 * @param tagId - ID of the tag to remove
	 */
	removePageTag(pageId: number, tagId: number) {
		return pageRemoveTag(this.options({ id: pageId, tagId }))
	}

	/**
	 * Trash a page in the given context (collective or public share)
	 *
	 * @param pageId - ID of the page to trash
	 */
	trashPage(pageId: number) {
		return pageTrash(this.options({ id: pageId }))
	}

	/**
	 * Restore the page with the given id from trash
	 *
	 * @param pageId - ID of the page to restore
	 */
	restorePage(pageId: number) {
		return pageTrashRestore(this.options({ id: pageId }))
	}

	/**
	 * Delete the page with the given id from trash
	 *
	 * @param pageId - ID of the page to trash
	 */
	deletePage(pageId: number) {
		return pageTrashDelete(this.options({ id: pageId }))
	}

	/**
	 * Get list of attachments for a page
	 *
	 * @param pageId - ID of the page to list attachments for
	 */
	getPageAttachments(pageId: number) {
		return pageGetAttachments(this.options({ id: pageId }))
	}

	/**
	 * Upload attachment of a page
	 *
	 * @param pageId - ID of the page that the attachment belongs to
	 * @param formData - The formData containing the attachment
	 */
	uploadAttachment(pageId: number, formData: FormData) {
		return pageUploadAttachment({
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
		return pageRenameAttachment(this.options({ id: pageId, attachmentId }, { name }))
	}

	/**
	 * Delete attachment of a page
	 *
	 * @param pageId - ID of the page that the attachment belongs to
	 * @param attachmentId - ID of the attachment to delete
	 */
	deleteAttachment(pageId: number, attachmentId: number) {
		return pageDeleteAttachment(this.options({ id: pageId, attachmentId }))
	}

	/**
	 * Restore attachment of a page from trash
	 *
	 * @param pageId - ID of the page that the attachment belongs to
	 * @param attachmentId - ID of the attachment to restore
	 */
	restoreAttachment(pageId: number, attachmentId: number) {
		return pageRestoreAttachment(this.options({ id: pageId, attachmentId }))
	}

	/**
	 * Perform index search on pages in given collective
	 *
	 * @param searchString string to search for
	 */
	contentSearch(searchString: string) {
		return pageContentSearch({
			...this.options(),
			query: { searchString },
		})
	}
}
