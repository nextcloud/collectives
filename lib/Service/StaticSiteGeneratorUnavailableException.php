<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

/**
 * The external static site generator is not reachable or temporarily unavailable, a later request may succeed.
 */
class StaticSiteGeneratorUnavailableException extends StaticSiteGeneratorException {
}
