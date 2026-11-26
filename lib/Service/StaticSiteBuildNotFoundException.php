<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

/**
 * The static site exists, but the build isn't its current one or its archive is gone.
 * Requests for it are expected from the static site generator, so they aren't throttled.
 */
class StaticSiteBuildNotFoundException extends NotFoundException {
}
