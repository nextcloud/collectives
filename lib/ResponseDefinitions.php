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
 *     sharePageId?: int,
 *     shareEditable: bool,
 *     userPageOrder: int,
 *     userShowMembers: bool,
 *     userShowRecenPages: bool,
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
 *     password: string,
 * }
 *
 * @psalm-type CollectivesPageInfo = array{
 *     id: int,
 *     lastUserId?: string,
 *     lastUserDisplayName?: string,
 *     emoji?: string,
 *     isFullWidth: bool,
 *     subpageOrder: list<int>,
 *     trashTimestamp?: int,
 *     title: string,
 *     timestamp: int,
 *     size: int,
 *     fileName: string,
 *     filePath: string,
 *     collectivePath: string,
 *     parentId: int,
 *     shareToken?: string,
 * }
 *
 * @psalm-type CollectivesPageAttachment = array{
 *     id: int,
 *     name: string,
 *     filesize: int,
 *     mimetype: string,
 *     timestamp: int,
 *     path: string,
 *     internalPath: string,
 *     hasPreview: bool,
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
