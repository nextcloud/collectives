/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	ShareDeletePageShareData,
	ShareUpdatePageShareData,
} from '../../client/types.gen.ts'

import {
	shareCreateCollectiveShare,
	shareCreatePageShare,
	shareDeleteCollectiveShare,
	shareDeletePageShare,
	shareGetCollectiveShares,
	shareUpdateCollectiveShare,
	shareUpdatePageShare,
} from '../../client/sdk.gen.ts'
import { defaultOptions, path } from './defaultOptions.ts'

/**
 * Get shares of a collective and its pages
 *
 * @param collectiveId Id of the collective
 */
export function getShares(collectiveId: number) {
	return shareGetCollectiveShares({
		...defaultOptions,
		path: { ...path, collectiveId },
	})
}

/**
 * Create a public collective share
 *
 * @param collectiveId Id of the collective to be shared
 * @param password Optional password for the share
 */
export function createCollectiveShare(collectiveId: number, password?: string) {
	return shareCreateCollectiveShare({
		...defaultOptions,
		path: { ...path, collectiveId },
		body: { password },
	})
}

/**
 * Create a public page share
 *
 * @param collectiveId Id of the collective the page belongs to
 * @param pageId Id of the page to be shared
 * @param password Optional password for the share
 */
export function createPageShare(collectiveId: number, pageId: number, password: string) {
	return shareCreatePageShare({
		...defaultOptions,
		path: { ...path, collectiveId, pageId },
		body: { password },
	})
}

/**
 * Update a public collective share
 *
 * @param share Share to update
 */
export function updateShare(share: ShareUpdatePageShareData['body'] & ShareUpdatePageShareData['path']) {
	const options = {
		...defaultOptions,
		path: { ...path, ...share },
		body: { editable: share.editable, password: share.password ?? undefined },
	}
	return share.pageId
		? shareUpdatePageShare(options)
		: shareUpdateCollectiveShare(options)
}

/**
 * Delete a public collective share
 *
 * @param share Share to update
 */
export function deleteShare(share: ShareDeletePageShareData['path']) {
	const options = {
		...defaultOptions,
		path: { ...path, ...share },
	}
	return share.pageId
		? shareDeletePageShare(options)
		: shareDeleteCollectiveShare(options)
}
