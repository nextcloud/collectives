<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\App\IAppManager;
use OCP\Server;

require_once __DIR__ . '/../vendor/autoload.php';

define('PHPUNIT_RUN', 1);

$nextcloud_source = getenv('NEXTCLOUD_SOURCE')
  ?: __DIR__ . '/../../..';
require_once $nextcloud_source . '/tests/bootstrap.php';

Server::get(IAppManager::class)->loadApp('collectives');
