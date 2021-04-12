<?php
/**
 * Nextcloud - Collectives
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

return [
	'routes' => [
		// collectives
		['name' => 'collective#index', 'url' => '/_collectives', 'verb' => 'GET'],
		['name' => 'collective#create', 'url' => '/_collectives', 'verb' => 'POST'],
		['name' => 'collective#trash', 'url' => '/_collectives/{id}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],

		// collectives trash
		['name' => 'trash#index', 'url' => '/_collectives/trash', 'verb' => 'GET'],
		['name' => 'trash#delete', 'url' => '/_collectives/trash/{id}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],
		['name' => 'trash#restore', 'url' => '/_collectives/trash/{id}', 'verb' => 'PATCH',
			'requirements' => ['id' => '\d+']],

		// pages
		['name' => 'page#index', 'url' => '/_collectives/{collectiveId}/_pages',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+']],
		['name' => 'page#get', 'url' => '/_collectives/{collectiveId}/_pages/{id}',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+',
				'id' => '\d+']],
		['name' => 'page#create', 'url' => '/_collectives/{collectiveId}/_pages',
			'verb' => 'POST', 'requirements' => ['collectiveId' => '\d+']],
		['name' => 'page#touch', 'url' => '/_collectives/{collectiveId}/_pages/{id}/touch',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+',
				'id' => '\d+']],
		['name' => 'page#rename', 'url' => '/_collectives/{collectiveId}/_pages/{id}',
			'verb' => 'PUT', 'requirements' => ['collectiveId' => '\d+',
				'id' => '\d+']],
		['name' => 'page#destroy', 'url' => '/_collectives/{collectiveId}/_pages/{id}',
			'verb' => 'DELETE', 'requirements' => ['collectiveId' => '\d+',
				'id' => '\d+']],

		// default route
		['name' => 'start#index', 'url' => '/{path}', 'verb' => 'GET',
			'requirements' => ['path' => '.*'],
			'defaults' => ['path' => '']],
	]
];
