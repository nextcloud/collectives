<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

/**
 * The slug of a static site is already used by another static site, slugs are unique across all collectives
 */
class StaticSiteSlugTakenException extends ServiceException {
}
