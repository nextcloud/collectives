<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		// pages search API
		['name' => 'page#contentSearch', 'url' => '/_api/{collectiveId}/_pages/search',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'filterString' => '\s+']],

		// pages API
		['name' => 'page#index', 'url' => '/_api/{collectiveId}/_pages',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+']],
		['name' => 'page#get', 'url' => '/_api/{collectiveId}/_pages/{id}',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#create', 'url' => '/_api/{collectiveId}/_pages/{parentId}',
			'verb' => 'POST', 'requirements' => ['collectiveId' => '\d+', 'parentId' => '\d+']],
		['name' => 'page#touch', 'url' => '/_api/{collectiveId}/_pages/{id}/touch',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#setFullWidth', 'url' => '/_api/{collectiveId}/_pages/{id}/fullWidth',
			'verb' => 'PUT', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
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
		['name' => 'page#getAttachments', 'url' => '/_api/{collectiveId}/_pages/{id}/attachments',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#getBacklinks', 'url' => '/_api/{collectiveId}/_pages/{id}/backlinks',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],

		// pages trash API
		['name' => 'pageTrash#index', 'url' => '/_api/{collectiveId}/_pages/trash',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+']],
		['name' => 'pageTrash#delete', 'url' => '/_api/{collectiveId}/_pages/trash/{id}',
			'verb' => 'DELETE', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'pageTrash#restore', 'url' => '/_api/{collectiveId}/_pages/trash/{id}',
			'verb' => 'PATCH', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],

		// template pages API
		['name' => 'template#index', 'url' => '/_api/{collectiveId}/_templates',
			'verb' => 'GET', 'requirements' => ['collectiveId' => '\d+']],
		['name' => 'template#create', 'url' => '/_api/{collectiveId}/_templates/{id}',
			'verb' => 'POST', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'template#delete', 'url' => '/_api/{collectiveId}/_templates/{id}',
			'verb' => 'DELETE', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'template#rename', 'url' => '/_api/{collectiveId}/_templates/{id}',
			'verb' => 'PUT', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'template#setEmoji', 'url' => '/_api/{collectiveId}/_templates/{id}/emoji',
			'verb' => 'PUT', 'requirements' => ['collectiveId' => '\d+', 'id' => '\d+']],

		// public pages search API
		['name' => 'publicPage#contentSearch', 'url' => '/_api/p/{token}/_pages/search',
			'verb' => 'GET', 'requirements' => ['filterString' => '\s+']],

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
		// Collectives API
		['name' => 'collective#index', 'url' => '/api/v{apiVersion}/collectives', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'collective#create', 'url' => '/api/v{apiVersion}/collectives', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'collective#update', 'url' => '/api/v{apiVersion}/collectives/{id}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'collective#editLevel', 'url' => '/api/v{apiVersion}/collectives/{id}/editLevel', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'collective#shareLevel', 'url' => '/api/v{apiVersion}/collectives/{id}/shareLevel', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'collective#pageMode', 'url' => '/api/v{apiVersion}/collectives/{id}/pageMode', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'collective#trash', 'url' => '/api/v{apiVersion}/collectives/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],

		// Collectives trash API
		['name' => 'trash#index', 'url' => '/api/v{apiVersion}/collectives/trash', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'trash#delete', 'url' => '/api/v{apiVersion}/collectives/trash/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'trash#restore', 'url' => '/api/v{apiVersion}/collectives/trash/{id}', 'verb' => 'PATCH',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],

		// Collective shares API
		['name' => 'share#getCollectiveShares', 'url' => '/api/v{apiVersion}/shares/{collectiveId}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'share#createPageShare', 'url' => '/api/v{apiVersion}/shares/{collectiveId}/pages/{pageId}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'pageId' => '\d+']],
		['name' => 'share#updatePageShare', 'url' => '/api/v{apiVersion}/shares/{collectiveId}/pages/{pageId}/{token}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'pageId' => '\d+']],
		['name' => 'share#deletePageShare', 'url' => '/api/v{apiVersion}/shares/{collectiveId}/pages/{pageId}/{token}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'pageId' => '\d+']],
		['name' => 'share#createCollectiveShare', 'url' => '/api/v{apiVersion}/shares/{collectiveId}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'share#updateCollectiveShare', 'url' => '/api/v{apiVersion}/shares/{collectiveId}/{token}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'share#deleteCollectiveShare', 'url' => '/api/v{apiVersion}/shares/{collectiveId}/{token}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],

		// Collective user settings API
		['name' => 'collectiveUserSettings#setPageOrder', 'url' => '/api/v{apiVersion}/userSettings/{collectiveId}/pageOrder', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'collectiveUserSettings#setShowMembers', 'url' => '/api/v{apiVersion}/userSettings/{collectiveId}/showMembers', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'collectiveUserSettings#setShowRecentPages', 'url' => '/api/v{apiVersion}/userSettings/{collectiveId}/showRecentPages', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'collectiveUserSettings#setFavoritePages', 'url' => '/api/v{apiVersion}/userSettings/{collectiveId}/favoritePages', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],

		// Session API
		['name' => 'session#create', 'url' => '/api/v{apiVersion}/session/{collectiveId}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'session#sync', 'url' => '/api/v{apiVersion}/session/{collectiveId}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'session#close', 'url' => '/api/v{apiVersion}/session/{collectiveId}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)']],

		// Settings API
		['name' => 'settings#getUserSetting', 'url' => '/api/v{apiVersion}/settings/user/{key}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'settings#setUserSetting', 'url' => '/api/v{apiVersion}/settings/user', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)']],

		// Public collectives API
		['name' => 'publicCollective#get', 'url' => '/api/v{apiVersion}/p/collectives/{token}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
	]
];
