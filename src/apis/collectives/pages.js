import axios from '@nextcloud/axios'
import { contentSearchUrl, pagesUrl } from './urls.js'

/**
 * Get all pages in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 */
export function getPages(context) {
	return axios.get(pagesUrl(context))
}

/**
 * Perform index search on pages in given collective
 *
 * @param {number} collectiveId collective to search in
 * @param {string} searchString string to search for
 */
export function contentSearchPages(collectiveId, searchString) {
	return axios.get(contentSearchUrl(collectiveId), { params: { searchString } })
}

/**
 * Get all trashed pages in the given context.
 *
 * @param {object} context - either the current collective or a share context
 */
export function getTrashPages(context) {
	return axios.get(pagesUrl(context, 'trash'))
}

/**
 * Create a new page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {object} page - properties of the new page
 */
export function createPage(context, page) {
	return axios.post(
		pagesUrl(context, page.parentId),
		page,
	)
}

/**
 * Get a page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to retrieve
 */
export function getPage(context, pageId) {
	return axios.get(pagesUrl(context, pageId))
}

/**
 * Touch a page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to touch
 */
export function touchPage(context, pageId) {
	return axios.get(pagesUrl(context, pageId, '/touch'))
}

/**
 * Rename a page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to rename
 * @param {string} title - New title for the page
 */
export function renamePage(context, pageId, title) {
	return axios.put(
		pagesUrl(context, pageId),
		{ title },
	)
}

/**
 * Copy a page inside the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to copy
 * @param {number} parentId - Id of the page to copy to
 * @param {number} index - Index for subpage order of parent page
 */
export function copyPage(context, pageId, parentId, index) {
	return axios.put(
		pagesUrl(context, pageId),
		{ parentId, index, copy: true },
	)
}

/**
 * Move a page inside the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to move
 * @param {number} parentId - Id of the page to move to
 * @param {number} index - Index for subpage order of parent page
 */
export function movePage(context, pageId, parentId, index) {
	return axios.put(
		pagesUrl(context, pageId),
		{ parentId, index },
	)
}

/**
 * Copy page to another collective
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to move
 * @param {number} collectiveId - Id of the new collective
 * @param {number} parentId - Id of the page to move to
 * @param {number} index - Index for subpage order of parent page
 */
export function copyPageToCollective(context, pageId, collectiveId, parentId, index) {
	return axios.put(
		pagesUrl(context, pageId, 'to', collectiveId),
		{ parentId, index, copy: true },
	)
}

/**
 * Move page to another collective
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to move
 * @param {number} collectiveId - Id of the new collective
 * @param {number} parentId - Id of the page to move to
 * @param {number} index - Index for subpage order of parent page
 */
export function movePageToCollective(context, pageId, collectiveId, parentId, index) {
	return axios.put(
		pagesUrl(context, pageId, 'to', collectiveId),
		{ parentId, index },
	)
}

/**
 * Set emoji for a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to update
 * @param {string} emoji - New emojie for the page
 */
export function setPageEmoji(context, pageId, emoji) {
	return axios.put(
		pagesUrl(context, pageId, 'emoji'),
		{ emoji },
	)
}

/**
 * Set subpageOrder for a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to update
 * @param {string} subpageOrder - New subpageOrdere for the page
 */
export function setPageSubpageOrder(context, pageId, subpageOrder) {
	return axios.put(
		pagesUrl(context, pageId, 'subpageOrder'),
		{ subpageOrder },
	)
}

/**
 * Trash a page in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to trash
 */
export function trashPage(context, pageId) {
	return axios.delete(pagesUrl(context, pageId))
}

/**
 * Restore the page with the given id from trash
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to restore
 */
export function restorePage(context, pageId) {
	return axios.patch(pagesUrl(context, '/trash', pageId))
}

/**
 * Delete the page with the given id from trash
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to trash
 */
export function deletePage(context, pageId) {
	return axios.delete(pagesUrl(context, '/trash', pageId))
}

/**
 * Get list of attachments for a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to list attachments for
 */
export function getPageAttachments(context, pageId) {
	return axios.get(pagesUrl(context, pageId, 'attachments'))
}

/**
 * Get list of backlinks for a page
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} pageId - Id of the page to list backlinks for
 */
export function getPageBacklinks(context, pageId) {
	return axios.get(pagesUrl(context, pageId, 'backlinks'))
}
