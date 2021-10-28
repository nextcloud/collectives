<?php
/**
 * Nextcloud - Collectives
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

return [
	'routes' => [
		// collectives API
		['name' => 'collective#index', 'url' => '/_api', 'verb' => 'GET'],
		['name' => 'collective#create', 'url' => '/_api', 'verb' => 'POST'],
		['name' => 'collective#update', 'url' => '/_api/{id}', 'verb' => 'PUT',
			'requirements' => ['id' => '\d+']],
		['name' => 'collective#trash', 'url' => '/_api/{id}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],
		['name' => 'collective#getShare', 'url' => '/_api/{id}/share', 'verb' => 'GET',
			'requirements' => ['id' => '\d+']],
		['name' => 'collective#createShare', 'url' => '/_api/{id}/share', 'verb' => 'POST',
			'requirements' => ['id' => '\d+']],
		['name' => 'collective#deleteShare', 'url' => '/_api/{id}/share/{token}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],

		// collectives trash API
		['name' => 'trash#index', 'url' => '/_api/trash', 'verb' => 'GET'],
		['name' => 'trash#delete', 'url' => '/_api/trash/{id}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],
		['name' => 'trash#restore', 'url' => '/_api/trash/{id}', 'verb' => 'PATCH',
			'requirements' => ['id' => '\d+']],

		// pages API
		['name' => 'page#index', 'url' => '/_api/{collectiveId}/_pages',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+']],
		['name' => 'page#get', 'url' => '/_api/{collectiveId}/_pages/parent/{parentId}/page/{id}',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'parentId' => '\d+', 'id' => '\d+']],
		['name' => 'page#create', 'url' => '/_api/{collectiveId}/_pages/parent/{parentId}',
			'verb' => 'POST', 'requirements' => ['collectiveId' => '\d+', 'parentId' => '\d+']],
		['name' => 'page#touch', 'url' => '/_api/{collectiveId}/_pages/parent/{parentId}/page/{id}/touch',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'parentId' => '\d+', 'id' => '\d+']],
		['name' => 'page#rename', 'url' => '/_api/{collectiveId}/_pages/parent/{parentId}/page/{id}',
			'verb' => 'PUT', 'requirements' => ['collectiveId' => '\d+', 'parentId' => '\d+', 'id' => '\d+']],
		['name' => 'page#delete', 'url' => '/_api/{collectiveId}/_pages/parent/{parentId}/page/{id}',
			'verb' => 'DELETE', 'requirements' => ['collectiveId' => '\d+', 'parentId' => '\d+', 'id' => '\d+']],
		['name' => 'page#getBacklinks', 'url' => '/_api/{collectiveId}/_pages/parent/{parentId}/page/{id}/backlinks',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'parentId' => '\d+', 'id' => '\d+']],

		// public collectives API
		['name' => 'publicCollective#get', 'url' => '/_api/p/{token}', 'verb' => 'GET'],

		// public pages API
		['name' => 'publicPage#index', 'url' => '/_api/p/{token}/_pages', 'verb' => 'GET'],
		['name' => 'publicPage#get', 'url' => '/_api/p/{token}/_pages/parent/{parentId}/page/{id}',
			'verb' => 'GET', 'requirements' => ['parentId' => '\d+', 'id' => '\d+']],
		['name' => 'publicPage#getBacklinks', 'url' => '/_api/p/{token}/_pages/parent/{parentId}/page/{id}/backlinks',
			'verb' => 'GET', 'requirements' => ['parentId' => '\d+', 'id' => '\d+']],

		// default public route (Vue.js frontend)
		['name' => 'publicStart#publicIndex', 'url' => '/p/{token}/{path}', 'verb' => 'GET',
			'requirements' => ['path' => '.*'],	'defaults' => ['path' => '']],

		// default route (Vue.js frontend)
		['name' => 'start#index', 'url' => '/{path}', 'verb' => 'GET',
			'requirements' => ['path' => '.*'],
			'defaults' => ['path' => '']],
	]
];
