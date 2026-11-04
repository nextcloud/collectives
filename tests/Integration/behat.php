<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Config\Config;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;

$ciContextArgs = [
	'baseUrl' => 'http://localhost:8080/index.php',
	'ocsUrl' => 'http://localhost:8080/ocs/v2.php',
	'publicUrl' => 'http://localhost:8080/public.php',
	'remoteUrl' => 'http://localhost:8080/remote.php',
];

return (new Config())
	->withProfile((new Profile('default'))
		->withSuite((new Suite('default'))
			->withPaths('%paths.base%/features')
			->addContext('FeatureContext', [
				'baseUrl' => 'https://nextcloud.local/index.php',
				'ocsUrl' => 'https://nextcloud.local/ocs/v2.php',
				'publicUrl' => 'https://nextcloud.local/public.php',
				'remoteUrl' => 'https://nextcloud.local/remote.php',
				'occPath' => 'nc-shell.sh master php occ',
			])))
	->withProfile((new Profile('ci'))
		->withSuite((new Suite('default'))
			->addContext('FeatureContext', $ciContextArgs)))
	->withProfile((new Profile('ci-encryption'))
		->withSuite((new Suite('default'))
			->addContext('FeatureContext', $ciContextArgs)
			->withFilter(new TagFilter('~@no-encryption'))));
