<?php
/**
 * Nextcloud - Wiki
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

return [
	'routes' => [
		// default route
		['name' => 'wiki#index', 'url' => '/', 'verb' => 'GET'],

		// pages
		['name' => 'page#index', 'url' => '/pages', 'verb' => 'GET'],
		['name' => 'page#create', 'url' => '/pages', 'verb' => 'POST'],
		['name' => 'page#update', 'url' => '/pages/{id}', 'verb' => 'PUT'],
		['name' => 'page#destroy', 'url' => '/pages/{id}', 'verb' => 'DELETE'],

		// API
		['name' => 'page_api#preflighted_cors', 'url' => '/api/0.1/{path}',
		 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
	]
];
