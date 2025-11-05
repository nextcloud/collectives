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

		// Service worker route
		['name' => 'start#serviceWorker', 'url' => '/service-worker.js', 'verb' => 'GET'],

		// Vue.js router public route (Vue.js frontend)
		['name' => 'publicStart#showAuthenticate', 'url' => '/p/{token}/authenticate/{redirect}', 'verb' => 'GET'],
		['name' => 'publicStart#authenticate', 'url' => '/p/{token}/authenticate/{redirect}', 'verb' => 'POST'],
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
		['name' => 'share#getCollectiveShares', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/shares', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'share#createPageShare', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{pageId}/shares', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'pageId' => '\d+']],
		['name' => 'share#updatePageShare', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{pageId}/shares/{token}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'pageId' => '\d+']],
		['name' => 'share#deletePageShare', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{pageId}/shares/{token}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'pageId' => '\d+']],
		['name' => 'share#createCollectiveShare', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/shares', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'share#updateCollectiveShare', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/shares/{token}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'share#deleteCollectiveShare', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/shares/{token}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],

		// Pages search API
		['name' => 'page#contentSearch', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/search', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],

		// Pages API
		['name' => 'page#index', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'page#get', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#create', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{parentId}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'parentId' => '\d+']],
		['name' => 'page#touch', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}/touch', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#setFullWidth', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}/fullWidth', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#moveOrCopy', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#moveOrCopyToCollective', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}/to/{newCollectiveId}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+', 'newCollectiveId' => '\d+']],
		['name' => 'page#setEmoji', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}/emoji', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#setSubpageOrder', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}/subpageOrder', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#addTag', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}/tags/{tagId}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+', 'tagId' => '\d+']],
		['name' => 'page#removeTag', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}/tags/{tagId}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+', 'tagId' => '\d+']],
		['name' => 'page#trash', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'page#getAttachments', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/{id}/attachments', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],

		// Pages trash API
		['name' => 'pageTrash#index', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/trash', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'pageTrash#delete', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/trash/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'pageTrash#restore', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/trash/{id}', 'verb' => 'PATCH',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],

		// Template pages API
		['name' => 'template#index', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/templates', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'template#create', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/templates/{id}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'template#delete', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/templates/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'template#rename', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/templates/{id}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'template#setEmoji', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/pages/templates/{id}/emoji', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],

		// Tags API
		['name' => 'tag#index', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/tags', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'tag#create', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/tags', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'tag#update', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/tags/{id}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],
		['name' => 'tag#delete', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/tags/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+', 'id' => '\d+']],

		// Collective user settings API
		['name' => 'collectiveUserSettings#setPageOrder', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/userSettings/pageOrder', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'collectiveUserSettings#setShowMembers', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/userSettings/showMembers', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'collectiveUserSettings#setShowRecentPages', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/userSettings/showRecentPages', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],
		['name' => 'collectiveUserSettings#setFavoritePages', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/userSettings/favoritePages', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'collectiveId' => '\d+']],

		// Session API
		['name' => 'session#create', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/sessions', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'session#sync', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/sessions', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'session#close', 'url' => '/api/v{apiVersion}/collectives/{collectiveId}/sessions', 'verb' => 'DELETE',
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
		['name' => 'publicPage#contentSearch', 'url' => '/api/v{apiVersion}/p/collectives/{token}/search', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],

		// Public pages API
		['name' => 'publicPage#index', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'publicPage#get', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#create', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{parentId}', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)', 'parentId' => '\d+']],
		['name' => 'publicPage#touch', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}/touch', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#setFullWidth', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}/fullWidth', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#moveOrCopy', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#setEmoji', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}/emoji', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#setSubpageOrder', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}/subpageOrder', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#addTag', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}/tags/{tagId}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+', 'tagId' => '\d+']],
		['name' => 'publicPage#removeTag', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}/tags/{tagId}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+', 'tagId' => '\d+']],
		['name' => 'publicPage#trash', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPage#getAttachments', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/{id}/attachments', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],

		// Public pages trash API
		['name' => 'publicPageTrash#index', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/trash', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'publicPageTrash#delete', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/trash/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicPageTrash#restore', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/trash/{id}', 'verb' => 'PATCH',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],

		// Public template pages API
		['name' => 'publicTemplate#index', 'url' => '/api/v{apiVersion}/p/collectives/{token}/pages/templates', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],

		// Public tags API
		['name' => 'publicTag#index', 'url' => '/api/v{apiVersion}/p/collectives/{token}/tags', 'verb' => 'GET',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'publicTag#create', 'url' => '/api/v{apiVersion}/p/collectives/{token}/tags', 'verb' => 'POST',
			'requirements' => ['apiVersion' => '(1.0)']],
		['name' => 'publicTag#update', 'url' => '/api/v{apiVersion}/p/collectives/{token}/tags/{id}', 'verb' => 'PUT',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
		['name' => 'publicTag#delete', 'url' => '/api/v{apiVersion}/p/collectives/{token}/tags/{id}', 'verb' => 'DELETE',
			'requirements' => ['apiVersion' => '(1.0)', 'id' => '\d+']],
	]
];
