<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives;

/**
 * @psalm-type CollectivesCollectiveShare = array{
 *     id: int,
 *     collectiveId: int,
 *     pageId: int,
 *     token: string,
 *     owner: string,
 *     editable: bool,
 *     password: string,
 * }
 */
class ResponseDefinitions {
}
