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
		['name' => 'page#index', 'url' => '/_pages/{wikiId}', 'verb' => 'GET',
			'requirements' => ['wikiId' => '\d+']],
		['name' => 'page#get', 'url' => '/_pages/{wikiId}/{id}', 'verb' => 'GET',
			'requirements' => ['wikiId' => '\d+', 'id' => '\d+']],
		['name' => 'page#create', 'url' => '/_pages/{wikiId}', 'verb' => 'POST',
			'requirements' => ['wikiId' => '\d+']],
		['name' => 'page#rename', 'url' => '/_pages/{wikiId}/{id}', 'verb' => 'PUT',
			'requirements' => ['wikiId' => '\d+', 'id' => '\d+']],
		['name' => 'page#destroy', 'url' => '/_pages/{wikiId}/{id}', 'verb' => 'DELETE',
			'requirements' => ['wikiId' => '\d+', 'id' => '\d+']],

		// wikis
		['name' => 'wiki#index', 'url' => '/_wikis', 'verb' => 'GET'],
		['name' => 'wiki#create', 'url' => '/_wikis', 'verb' => 'POST'],
		['name' => 'wiki#destroy', 'url' => '/_wikis/{id}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],

		// default route
		['name' => 'start#index', 'url' => '/{path}', 'verb' => 'GET',
			'requirements' => ['path' => '.*'],
			'defaults' => ['path' => '']],
	]
];
