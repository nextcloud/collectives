<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

/**
 * The external static site generator didn't accept the request, as it is still processing a
 * previous request for the static site. A later request may succeed.
 */
class StaticSiteGeneratorBusyException extends StaticSiteGeneratorException {
}
