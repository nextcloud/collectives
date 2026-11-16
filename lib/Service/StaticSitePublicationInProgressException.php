<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

/**
 * A static site of the collective is being published, only one publication per collective may run at a time
 */
class StaticSitePublicationInProgressException extends ServiceException {
}
