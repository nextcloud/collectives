/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Collective.php jsonSerialize()
export interface Collective {
	id: number
	slug: string | null
	circleId: string
	emoji: string | null
	trashTimestamp: number | null
	pageMode: number
	name: string
	level: number
	editPermissionLevel: number
	sharePermissionLevel: number
	canEdit: boolean
	canShare: boolean
	shareToken: string | null
	isPageShare: boolean
	sharePageId: number | null
	shareEditable: boolean
	userPageOrder: number
	userShowMembers: boolean
	userShowRecentPages: boolean
	userFavoritePages: number[]
	canLeave: boolean
}

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
	collectiveName: string | null
	parentId: number
	shareToken: string | null
	linkedPageIds: number[]
}
