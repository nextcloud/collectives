<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

/**
 * The external static site generator is not configured, not reachable or rejected a request.
 *
 * The message is translated and can be shown to the user.
 */
class StaticSiteGeneratorException extends ServiceException {
}
