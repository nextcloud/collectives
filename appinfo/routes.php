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
		['name' => 'collective#editLevel', 'url' => '/_api/{id}/editLevel', 'verb' => 'PUT',
			'requirements' => ['id' => '\d+']],
		['name' => 'collective#shareLevel', 'url' => '/_api/{id}/shareLevel', 'verb' => 'PUT',
			'requirements' => ['id' => '\d+']],
		['name' => 'collective#pageMode', 'url' => '/_api/{id}/pageMode', 'verb' => 'PUT',
			'requirements' => ['id' => '\d+']],
		['name' => 'collective#trash', 'url' => '/_api/{id}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],

		// collectives trash API
		['name' => 'trash#index', 'url' => '/_api/trash', 'verb' => 'GET'],
		['name' => 'trash#delete', 'url' => '/_api/trash/{id}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],
		['name' => 'trash#restore', 'url' => '/_api/trash/{id}', 'verb' => 'PATCH',
			'requirements' => ['id' => '\d+']],

		// collectives userSettings API
		['name' => 'collectiveUserSettings#pageOrder', 'url' => '/_api/{id}/_userSettings/pageOrder', 'verb' => 'PUT',
			'requirements' => ['id' => '\d+']],
		['name' => 'collectiveUserSettings#showRecentPages', 'url' => '/_api/{id}/_userSettings/showRecentPages', 'verb' => 'PUT',
			'requirements' => ['id' => '\d+']],

		// share API
		['name' => 'share#getCollectiveShares', 'url' => '/_api/{collectiveId}/shares', 'verb' => 'GET',
			'requirements' => ['collectiveId' => '\d+']],
		['name' => 'share#createCollectiveShare', 'url' => '/_api/{collectiveId}/share', 'verb' => 'POST',
			'requirements' => ['collectiveId' => '\d+']],
		['name' => 'share#updateCollectiveShare', 'url' => '/_api/{collectiveId}/share/{token}', 'verb' => 'PUT',
			'requirements' => ['collectiveId' => '\d+']],
		['name' => 'share#deleteCollectiveShare', 'url' => '/_api/{collectiveId}/share/{token}', 'verb' => 'DELETE',
			'requirements' => ['collectiveId' => '\d+']],
		['name' => 'share#createPageShare', 'url' => '/_api/{collectiveId}/_pages/{pageId}/share', 'verb' => 'POST',
			'requirements' => ['collectiveId' => '\d+', 'pageId' => '\d+']],
		['name' => 'share#updatePageShare', 'url' => '/_api/{collectiveId}/_pages/{pageId}/share/{token}', 'verb' => 'PUT',
			'requirements' => ['collectiveId' => '\d+', 'pageId' => '\d+']],
		['name' => 'share#deletePageShare', 'url' => '/_api/{collectiveId}/_pages/{pageId}/share/{token}', 'verb' => 'DELETE',
			'requirements' => ['collectiveId' => '\d+', 'pageId' => '\d+']],

		// pages API
		['name' => 'page#index', 'url' => '/_api/{collectiveId}/_pages',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+']],
		['name' => 'page#get', 'url' => '/_api/{collectiveId}/_pages/{id}',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#create', 'url' => '/_api/{collectiveId}/_pages/{parentId}',
			'verb' => 'POST', 'requirements' => ['collectiveId' => '\d+', 'parentId' => '\d+']],
		['name' => 'page#touch', 'url' => '/_api/{collectiveId}/_pages/{id}/touch',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#contentSearch', 'url' => '/_api/{collectiveId}/_pages/search',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'filterString' => '\s+']],
		['name' => 'page#moveOrCopy', 'url' => '/_api/{collectiveId}/_pages/{id}',
			'verb' => 'PUT', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#moveOrCopyToCollective', 'url' => '/_api/{collectiveId}/_pages/{id}/to/{newCollectiveId}',
			'verb' => 'PUT', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+', 'newCollectiveId' => '\d+']],
		['name' => 'page#setEmoji', 'url' => '/_api/{collectiveId}/_pages/{id}/emoji',
			'verb' => 'PUT', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#setSubpageOrder', 'url' => '/_api/{collectiveId}/_pages/{id}/subpageOrder',
			'verb' => 'PUT', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#trash', 'url' => '/_api/{collectiveId}/_pages/{id}',
			'verb' => 'DELETE', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#getBacklinks', 'url' => '/_api/{collectiveId}/_pages/{id}/backlinks',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#getAttachments', 'url' => '/_api/{collectiveId}/_pages/{id}/attachments',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],

		// pages trash API
		['name' => 'pageTrash#index', 'url' => '/_api/{collectiveId}/_pages/trash', 'verb' => 'GET'],
		['name' => 'pageTrash#delete', 'url' => '/_api/{collectiveId}/_pages/trash/{id}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],
		['name' => 'pageTrash#restore', 'url' => '/_api/{collectiveId}/_pages/trash/{id}', 'verb' => 'PATCH',
			'requirements' => ['id' => '\d+']],

		// public collectives API
		['name' => 'publicCollective#get', 'url' => '/_api/p/{token}', 'verb' => 'GET'],

		// public pages API
		['name' => 'publicPage#index', 'url' => '/_api/p/{token}/_pages', 'verb' => 'GET'],
		['name' => 'publicPage#get', 'url' => '/_api/p/{token}/_pages/{id}',
			'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'publicPage#create', 'url' => '/_api/p/{token}/_pages/{parentId}',
			'verb' => 'POST', 'requirements' => ['parentId' => '\d+']],
		['name' => 'publicPage#touch', 'url' => '/_api/p/{token}/_pages/{id}/touch',
			'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'publicPage#moveOrCopy', 'url' => '/_api/p/{token}/_pages/{id}',
			'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'publicPage#setEmoji', 'url' => '/_api/p/{token}/_pages/{id}/emoji',
			'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'publicPage#setSubpageOrder', 'url' => '/_api/p/{token}/_pages/{id}/subpageOrder',
			'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'publicPage#trash', 'url' => '/_api/p/{token}/_pages/{id}',
			'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
		['name' => 'publicPage#getAttachments', 'url' => '/_api/p/{token}/_pages/{id}/attachments',
			'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'publicPage#getBacklinks', 'url' => '/_api/p/{token}/_pages/{id}/backlinks',
			'verb' => 'GET', 'requirements' => ['id' => '\d+']],

		// public pages trash API
		['name' => 'publicPageTrash#index', 'url' => '/_api/p/{token}/_pages/trash', 'verb' => 'GET'],
		['name' => 'publicPageTrash#delete', 'url' => '/_api/p/{token}/_pages/trash/{id}', 'verb' => 'DELETE',
			'requirements' => ['id' => '\d+']],
		['name' => 'publicPageTrash#restore', 'url' => '/_api/p/{token}/_pages/trash/{id}', 'verb' => 'PATCH',
			'requirements' => ['id' => '\d+']],

		// default Vue.js router route (Vue.js frontend)
		['name' => 'start#index', 'url' => '/', 'verb' => 'GET'],

		// Vue.js router public route (Vue.js frontend)
		['name' => 'publicStart#showAuthenticate', 'url' => '/p/{token}/authenticate/{redirect}', 'verb' => 'GET'],
		['name' => 'publicStart#authenticate', 'url' => '/p/{token}/authenticate/{redirect}', 'verb' => 'POST'],
		// TODO: Remove the two uppercase PublicStart entries once Nextcloud 28 support gets removed
		// See
		['name' => 'PublicStart#showAuthenticate', 'url' => '/p/{token}/authenticate/{redirect}', 'verb' => 'GET'],
		['name' => 'PublicStart#authenticate', 'url' => '/p/{token}/authenticate/{redirect}', 'verb' => 'POST'],
		['name' => 'publicStart#showShare', 'url' => '/p/{token}/{path}', 'verb' => 'GET',
			'requirements' => ['path' => '.*'],	'defaults' => ['path' => '']],

		// Vue.js router route (Vue.js frontend)
		['name' => 'start#indexPath', 'url' => '/{path}', 'verb' => 'GET',
			'requirements' => ['path' => '.*'],
			'defaults' => ['path' => '/']],
	],
	'ocs' => [
		// User settings API
		['name' => 'settings#getUserSetting', 'url' => '/api/v{apiVersion}/settings/user/{key}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '1.0']],
		['name' => 'settings#setUserSetting', 'url' => '/api/v{apiVersion}/settings/user', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '1.0']],

		// Session API
		['name' => 'session#create', 'url' => '/api/v{apiVersion}/session/{collectiveId}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '1.0']],
		['name' => 'session#sync', 'url' => '/api/v{apiVersion}/session/{collectiveId}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '1.0']],
		['name' => 'session#close', 'url' => '/api/v{apiVersion}/session/{collectiveId}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '1.0']],
	]
];
