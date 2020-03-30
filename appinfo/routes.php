<?php
/**
 * Nextcloud - Wiki
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

return [
	'resources' => [
                // pages
		'page' => ['url' => '/pages'],
		'page_api' => ['url' => '/api/0.1/pages']
	],
	'routes' => [
		// wikis
		['name' => 'wiki#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'wiki#show', 'url' => '/show', 'verb' => 'GET'],

		// api
		['name' => 'page_api#preflighted_cors', 'url' => '/api/0.1/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
    ]
];
