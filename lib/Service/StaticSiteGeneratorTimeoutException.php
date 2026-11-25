<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

/**
 * The external static site generator didn't answer after the request was sent, e.g. it timed out.
 * It may have got and accepted the request anyway.
 */
class StaticSiteGeneratorTimeoutException extends StaticSiteGeneratorUnavailableException {
}
