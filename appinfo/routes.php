<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
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

		// Pages search API
		['name' => 'page#contentSearch', 'url' => '/api/v{apiVersion}/search/{collectiveId}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],

		// Pages API
		['name' => 'page#index', 'url' => '/api/v{apiVersion}/pages/{collectiveId}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'page#get', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#create', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{parentId}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'parentId' => '\d+']],
		['name' => 'page#touch', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}/touch', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#setFullWidth', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}/fullWidth', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#moveOrCopy', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#moveOrCopyToCollective', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}/to/{newCollectiveId}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+', 'newCollectiveId' => '\d+']],
		['name' => 'page#setEmoji', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}/emoji', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#setSubpageOrder', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}/subpageOrder', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#trash', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#getAttachments', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}/attachments', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#getBacklinks', 'url' => '/api/v{apiVersion}/pages/{collectiveId}/{id}/backlinks', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],

		// Pages trash API
		['name' => 'pageTrash#index', 'url' => '/api/v{apiVersion}/pages/trash/{collectiveId}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'pageTrash#delete', 'url' => '/api/v{apiVersion}/pages/trash/{collectiveId}/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'pageTrash#restore', 'url' => '/api/v{apiVersion}/pages/trash/{collectiveId}/{id}', 'verb' => 'PATCH',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],

		// Template pages API
		['name' => 'template#index', 'url' => '/api/v{apiVersion}/pages/templates/{collectiveId}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'template#create', 'url' => '/api/v{apiVersion}/pages/templates/{collectiveId}/{id}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'template#delete', 'url' => '/api/v{apiVersion}/pages/templates/{collectiveId}/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'template#rename', 'url' => '/api/v{apiVersion}/pages/templates/{collectiveId}/{id}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'template#setEmoji', 'url' => '/api/v{apiVersion}/pages/templates/{collectiveId}/{id}/emoji', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],

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

		// Public pages search API
		['name' => 'publicPage#contentSearch', 'url' => '/api/v{apiVersion}/p/search/{token}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],

		// Public pages API
		['name' => 'publicPage#index', 'url' => '/api/v{apiVersion}/p/pages/{token}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'publicPage#get', 'url' => '/api/v{apiVersion}/p/pages/{token}/{id}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#create', 'url' => '/api/v{apiVersion}/p/pages/{token}/{parentId}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'parentId' => '\d+']],
		['name' => 'publicPage#touch', 'url' => '/api/v{apiVersion}/p/pages/{token}/{id}/touch', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#setFullWidth', 'url' => '/api/v{apiVersion}/p/pages/{token}/{id}/fullWidth', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#moveOrCopy', 'url' => '/api/v{apiVersion}/p/pages/{token}/{id}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#setEmoji', 'url' => '/api/v{apiVersion}/p/pages/{token}/{id}/emoji', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#setSubpageOrder', 'url' => '/api/v{apiVersion}/p/pages/{token}/{id}/subpageOrder', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#trash', 'url' => '/api/v{apiVersion}/p/pages/{token}/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#getAttachments', 'url' => '/api/v{apiVersion}/p/pages/{token}/{id}/attachments', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#getBacklinks', 'url' => '/api/v{apiVersion}/p/pages/{token}/{id}/backlinks', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],

		// Public pages trash API
		['name' => 'publicPageTrash#index', 'url' => '/api/v{apiVersion}/p/pages/trash/{token}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'publicPageTrash#delete', 'url' => '/api/v{apiVersion}/p/pages/trash/{token}/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPageTrash#restore', 'url' => '/api/v{apiVersion}/p/pages/trash/{token}/{id}', 'verb' => 'PATCH',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],

		// Public template pages API
		['name' => 'publicTemplate#index', 'url' => '/api/v{apiVersion}/p/pages/templates/{token}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
	]
];
