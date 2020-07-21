<?php
/**
 * Nextcloud - Wiki
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

return [
	'routes' => [
		// pages
		['name' => 'page#index', 'url' => '/_pages', 'verb' => 'GET'],
		['name' => 'page#get', 'url' => '/_pages/{id}', 'verb' => 'GET'],
		['name' => 'page#create', 'url' => '/_pages', 'verb' => 'POST'],
		['name' => 'page#rename', 'url' => '/_pages/{id}', 'verb' => 'PUT',
		 'requirements' => ['id' => '\d+']],
		['name' => 'page#destroy', 'url' => '/_pages/{id}', 'verb' => 'DELETE',
		 'requirements' => ['id' => '\d+']],

		// circles
		['name' => 'wiki#list', 'url' => '/_wikis', 'verb' => 'GET'],
		['name' => 'wiki#create', 'url' => '/_wikis', 'verb' => 'POST'],

		// default route
		['name' => 'wiki#index', 'url' => '/{path}', 'verb' => 'GET',
		 'requirements' => ['path' => '.*'],
		 'defaults' => ['path' => '']],
	]
];
