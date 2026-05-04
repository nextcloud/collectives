/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PublicPageCreateData } from '../../client/types.gen.ts'
import type { Context } from './Client.ts'

import { clientForContextFactory } from './Client.ts'
import Page from './Page.ts'
import PublicPage from './PublicPage.ts'

const pageClient = clientForContextFactory({ forCollective: Page, forShare: PublicPage })

/**
 * Get all pages in the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 */
export function getPages(context: Context) {
	return pageClient(context).getPages()
}

/**
 * Get all trashed pages in the given context.
 *
 * @param context - either the current collective or a share context
 */
export function getTrashPages(context: Context) {
	return pageClient(context).getTrashPages()
}

/**
 * Create a new page in the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 * @param page - properties of the new page
 */
export function createPage(context: Context, page: PublicPageCreateData['body'] & { parentId: number }) {
	return pageClient(context).createPage(page)
}

/**
 * Get a page in the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to retrieve
 */
export function getPage(context: Context, pageId: number) {
	return pageClient(context).getPage(pageId)
}

/**
 * Touch a page in the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to touch
 */
export function touchPage(context: Context, pageId: number) {
	return pageClient(context).touchPage(pageId)
}

/**
 * Rename a page in the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to rename
 * @param title - New title for the page
 */
export function renamePage(context: Context, pageId: number, title: string) {
	return pageClient(context).renamePage(pageId, title)
}

/**
 * Copy a page inside the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to copy
 * @param parentId - ID of the page to copy to
 * @param index - Index for subpage order of parent page
 */
export function copyPage(context: Context, pageId: number, parentId: number, index: number) {
	return pageClient(context).copyPage(pageId, parentId, index)
}

/**
 * Move a page inside the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to move
 * @param parentId - ID of the page to move to
 * @param index - Index for subpage order of parent page
 */
export function movePage(context: Context, pageId: number, parentId: number, index: number) {
	return pageClient(context).movePage(pageId, parentId, index)
}

/**
 * Copy page to another collective
 *
 * @param context - the current collective
 * @param context.collectiveId - id of the source collective
 * @param pageId - ID of the page to move
 * @param newCollectiveId - ID of the new collective
 * @param parentId - ID of the page to move to
 * @param index - Index for subpage order of parent page
 */
export function copyPageToCollective(context: { collectiveId: number }, pageId: number, newCollectiveId: number, parentId: number, index: number) {
	return (new Page(context)).copyPageToCollective(pageId, newCollectiveId, parentId, index)
}

/**
 * Move page to another collective
 *
 * @param context - the current collective
 * @param context.collectiveId - id of the source collective
 * @param pageId - ID of the page to move
 * @param newCollectiveId - ID of the new collective
 * @param parentId - ID of the page to move to
 * @param index - Index for subpage order of parent page
 */
export function movePageToCollective(context: { collectiveId: number }, pageId: number, newCollectiveId: number, parentId: number, index: number) {
	return (new Page(context)).movePageToCollective(pageId, newCollectiveId, parentId, index)
}

/**
 * Set emoji for a page
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to update
 * @param emoji - New emojie for the page
 */
export function setPageEmoji(context: Context, pageId: number, emoji: string) {
	return pageClient(context).setPageEmoji(pageId, emoji)
}

/**
 * Set full width for a page
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to update
 * @param fullWidth - Full width for the page
 */
export function setFullWidth(context: Context, pageId: number, fullWidth: boolean) {
	return pageClient(context).setFullWidth(pageId, fullWidth)
}

/**
 * Set subpageOrder for a page
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to update
 * @param subpageOrder - New subpageOrdere for the page
 */
export function setPageSubpageOrder(context: Context, pageId: number, subpageOrder: string) {
	return pageClient(context).setPageSubpageOrder(pageId, subpageOrder)
}

/**
 * Add tag to a page
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to update
 * @param tagId - ID of the tag to add
 */
export function addPageTag(context: Context, pageId: number, tagId: number) {
	return pageClient(context).addPageTag(pageId, tagId)
}

/**
 * Remove tag from a page
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to update
 * @param tagId - ID of the tag to remove
 */
export function removePageTag(context: Context, pageId: number, tagId: number) {
	return pageClient(context).removePageTag(pageId, tagId)
}

/**
 * Trash a page in the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to trash
 */
export function trashPage(context: Context, pageId: number) {
	return pageClient(context).trashPage(pageId)
}

/**
 * Restore the page with the given id from trash
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to restore
 */
export function restorePage(context: Context, pageId: number) {
	return pageClient(context).restorePage(pageId)
}

/**
 * Delete the page with the given id from trash
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to trash
 */
export function deletePage(context: Context, pageId: number) {
	return pageClient(context).deletePage(pageId)
}

/**
 * Get list of attachments for a page
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page to list attachments for
 */
export function getPageAttachments(context: Context, pageId: number) {
	return pageClient(context).getPageAttachments(pageId)
}

/**
 * Upload attachment of a page
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page that the attachment belongs to
 * @param formData - The formData containing the attachment
 */
export function uploadAttachment(context: Context, pageId: number, formData: FormData) {
	return pageClient(context).uploadAttachment(pageId, formData)
}

/**
 * Rename attachment of a page
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page that the attachment belongs to
 * @param attachmentId - ID of the attachment to rename
 * @param name - Target name of the attachment
 */
export function renameAttachment(context: Context, pageId: number, attachmentId: number, name: string) {
	return pageClient(context).renameAttachment(pageId, attachmentId, name)
}

/**
 * Delete attachment of a page
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page that the attachment belongs to
 * @param attachmentId - ID of the attachment to delete
 */
export function deleteAttachment(context: Context, pageId: number, attachmentId: number) {
	return pageClient(context).deleteAttachment(pageId, attachmentId)
}

/**
 * Restore attachment of a page from trash
 *
 * @param context - either the current collective or a share context
 * @param pageId - ID of the page that the attachment belongs to
 * @param attachmentId - ID of the attachment to restore
 */
export function restoreAttachment(context: Context, pageId: number, attachmentId: number) {
	return pageClient(context).restoreAttachment(pageId, attachmentId)
}

/**
 * Perform index search on pages in given collective
 *
 * @param context - either the current collective or a share context
 * @param searchString string to search for
 */
export function contentSearch(context: Context, searchString: string) {
	return pageClient(context).contentSearch(searchString)
}
