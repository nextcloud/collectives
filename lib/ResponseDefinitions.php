<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives;

/**
 * @psalm-type CollectivesCollective = array{
 *     id: int,
 *     slug?: string,
 *     circleId: string,
 *     emoji?: string,
 *     trashTimestamp?: int,
 *     pageMode: int,
 *     name: string,
 *     level: int,
 *     editPermissionLevel: int,
 *     sharePermissionLevel: int,
 *     canEdit: bool,
 *     canShare: bool,
 *     shareToken?: string,
 *     isPageShare: bool,
 *     sharePageId: int,
 *     shareEditable: bool,
 *     userPageOrder: int,
 *     userShowMembers: bool,
 *     userShowRecentPages: bool,
 *     userFavoritePages: list<int>,
 *     canLeave: bool,
 * }
 *
 * @psalm-type CollectivesCollectiveShare = array{
 *     id: int,
 *     collectiveId: int,
 *     pageId: int,
 *     token: string,
 *     owner: string,
 *     editable: bool,
 *     hasPassword: bool,
 * }
 *
 * @psalm-type CollectivesPageInfo = array{
 *     id: int,
 *     slug?: string,
 *     lastUserId?: string,
 *     lastUserDisplayName?: string,
 *     emoji?: string,
 *     // Custom subpage order. Not guaranteed to contain all subpages of this page.
 *     subpageOrder: list<int>,
 *     isFullWidth: bool,
 *     tags: list<int>,
 *     trashTimestamp?: int,
 *     title: string,
 *     timestamp: int,
 *     size: int,
 *     fileName: string,
 *     filePath: string,
 *     filePathString: string,
 *     collectivePath?: string,
 *     collectiveNameWithEmoji?: string,
 *     parentId: int,
 *     shareToken?: string,
 *     linkedPageIds: list<int>,
 * }
 *
 * @psalm-type CollectivesPageAttachment = array{
 *     id: int,
 *     name: string,
 *     filesize: int,
 *     mimetype: string,
 *     timestamp: int,
 *     path: string,
 *     src: string,
 *     internalPath: string,
 *     hasPreview: bool,
 *     type: 'text'|'folder',
 * }
 *
 * @psalm-type CollectivesTag = array{
 *     id: int,
 *     collectiveId: int,
 *     name: string,
 *     color: string,
 *  }
 */
class ResponseDefinitions {
}
