/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// PageInfo.php jsonSerialize()
export interface PageInfo {
	id: number
	slug: string | null
	lastUserId: string | null
	lastUserDisplayName: string | null
	emoji: string | null
	subpageOrder: number[]
	isFullWidth: boolean
	tags: string[]
	trashTimestamp: number | null
	title: string
	timestamp: number
	size: number
	fileName: string
	filePath: string
	filePathString: string
	collectivePath: string | null
	parentId: number
	shareToken: string | null
	linkedPageIds: number[]
}
