/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * URL for the pages API inside the given context.
 *
 * @param {object} context - either the current collective or a share context
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function pagesApiUrl(context, ...parts) {
	return context.isPublic
		? apiUrl('v1.0', 'p', 'collectives', context.shareTokenParam, 'pages', ...parts)
		: apiUrl('v1.0', 'collectives', context.collectiveId, 'pages', ...parts)
}

/**
 * URL for the page trash API
 *
 * @param {object} context - either the current collective or a share context
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function pagesTrashApiUrl(context, ...parts) {
	return pagesApiUrl(context, 'trash', ...parts)
}

/**
 * URL for the page search API
 *
 * @param {object} context - either the current collective or a share context
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function searchApiUrl(context, ...parts) {
	return context.isPublic
		? apiUrl('v1.0', 'p', 'collectives', context.shareTokenParam, 'search', ...parts)
		: apiUrl('v1.0', 'collectives', context.collectiveId, 'search', ...parts)
}

/**
 * Get all pages in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 */
export function getPages(context) {
	return axios.get(pagesApiUrl(context))
}

/**
 * Get all trashed pages in the given context.
 *
 * @param {object} context - either the current collective or a share context
 */
export function getTrashPages(context) {
	return axios.get(pagesTrashApiUrl(context))
}

/**
 * Create a new page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {object} page - properties of the new page
 */
export function createPage(context, page) {
	return axios.post(
		pagesApiUrl(context, page.parentId),
		page,
	)
}

/**
 * Get a page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to retrieve
 */
export function getPage(context, pageId) {
	return axios.get(pagesApiUrl(context, pageId))
}

/**
 * Touch a page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to touch
 */
export function touchPage(context, pageId) {
	return axios.get(pagesApiUrl(context, pageId, 'touch'))
}

/**
 * Rename a page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to rename
 * @param {string} title - New title for the page
 */
export function renamePage(context, pageId, title) {
	return axios.put(
		pagesApiUrl(context, pageId),
		{ title },
	)
}

/**
 * Copy a page inside the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to copy
 * @param {number} parentId - ID of the page to copy to
 * @param {number} index - Index for subpage order of parent page
 */
export function copyPage(context, pageId, parentId, index) {
	return axios.put(
		pagesApiUrl(context, pageId),
		{ parentId, index, copy: true },
	)
}

/**
 * Move a page inside the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to move
 * @param {number} parentId - ID of the page to move to
 * @param {number} index - Index for subpage order of parent page
 */
export function movePage(context, pageId, parentId, index) {
	return axios.put(
		pagesApiUrl(context, pageId),
		{ parentId, index },
	)
}

/**
 * Copy page to another collective
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to move
 * @param {number} collectiveId - ID of the new collective
 * @param {number} parentId - ID of the page to move to
 * @param {number} index - Index for subpage order of parent page
 */
export function copyPageToCollective(context, pageId, collectiveId, parentId, index) {
	return axios.put(
		pagesApiUrl(context, pageId, 'to', collectiveId),
		{ parentId, index, copy: true },
	)
}

/**
 * Move page to another collective
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to move
 * @param {number} collectiveId - ID of the new collective
 * @param {number} parentId - ID of the page to move to
 * @param {number} index - Index for subpage order of parent page
 */
export function movePageToCollective(context, pageId, collectiveId, parentId, index) {
	return axios.put(
		pagesApiUrl(context, pageId, 'to', collectiveId),
		{ parentId, index },
	)
}

/**
 * Set emoji for a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to update
 * @param {string} emoji - New emojie for the page
 */
export function setPageEmoji(context, pageId, emoji) {
	return axios.put(
		pagesApiUrl(context, pageId, 'emoji'),
		{ emoji },
	)
}

/**
 * Set full width for a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to update
 * @param {boolean} fullWidth - Full width for the page
 */
export function setFullWidth(context, pageId, fullWidth) {
	return axios.put(
		pagesApiUrl(context, pageId, 'fullWidth'),
		{ fullWidth },
	)
}

/**
 * Set subpageOrder for a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to update
 * @param {string} subpageOrder - New subpageOrdere for the page
 */
export function setPageSubpageOrder(context, pageId, subpageOrder) {
	return axios.put(
		pagesApiUrl(context, pageId, 'subpageOrder'),
		{ subpageOrder },
	)
}

/**
 * Add tag to a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to update
 * @param {number} tagId - ID of the tag to add
 */
export function addPageTag(context, pageId, tagId) {
	return axios.put(pagesApiUrl(context, pageId, 'tags', tagId))
}

/**
 * Remove tag from a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to update
 * @param {number} tagId - ID of the tag to remove
 */
export function removePageTag(context, pageId, tagId) {
	return axios.delete(pagesApiUrl(context, pageId, 'tags', tagId))
}

/**
 * Trash a page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to trash
 */
export function trashPage(context, pageId) {
	return axios.delete(pagesApiUrl(context, pageId))
}

/**
 * Restore the page with the given id from trash
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to restore
 */
export function restorePage(context, pageId) {
	return axios.patch(pagesTrashApiUrl(context, pageId))
}

/**
 * Delete the page with the given id from trash
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to trash
 */
export function deletePage(context, pageId) {
	return axios.delete(pagesTrashApiUrl(context, pageId))
}

/**
 * Get list of attachments for a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - ID of the page to list attachments for
 */
export function getPageAttachments(context, pageId) {
	return axios.get(pagesApiUrl(context, pageId, 'attachments'))
}

/**
 * Perform index search on pages in given collective
 *
 * @param {object} context - either the current collective or a share context
 * @param {string} searchString string to search for
 */
export function contentSearch(context, searchString) {
	return axios.get(searchApiUrl(context), { params: { searchString } })
}
