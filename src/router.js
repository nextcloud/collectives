import { generateUrl } from '@nextcloud/router'
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createRouter, createWebHistory } from 'vue-router'
import CollectivePrintView from './views/CollectivePrintView.vue'
import CollectiveView from './views/CollectiveView.vue'
import HomeView from './views/HomeView.vue'

const routes = [
	{
		path: '/',
		component: HomeView,
	},
	{
		path: '/_/print/:collectiveSlug-:collectiveId(\\d+)',
		component: CollectivePrintView,
		props: (route) => route.params,
	},
	{
		path: '/_/print/:collective',
		component: CollectivePrintView,
		props: (route) => route.params,
	},
	{
		path: '/p/:token/print/:collectiveSlug-:collectiveId(\\d+)',
		component: CollectivePrintView,
		props: (route) => route.params,
	},
	{
		path: '/p/:token/print/:collective',
		component: CollectivePrintView,
		props: (route) => route.params,
	},
	{
		path: '/p/:token/:collectiveSlug-:collectiveId(\\d+)',
		component: CollectiveView,
		props: (route) => route.params,
		children: [
			{ path: ':pageSlug-:pageId(\\d+)', component: CollectiveView },
			{ path: ':page(.*)', component: CollectiveView },
		],
	},
	{
		path: '/p/:token/:collective',
		component: CollectiveView,
		props: (route) => route.params,
		children: [
			{ path: ':pageSlug-:pageId(\\d+)', component: CollectiveView },
			{ path: ':page(.*)', component: CollectiveView },
		],
	},
	{
		path: '/:collectiveSlug-:collectiveId(\\d+)',
		component: CollectiveView,
		props: (route) => route.params,
		children: [
			{ path: ':pageSlug-:pageId(\\d+)', component: CollectiveView },
			{ path: ':page(.*)', component: CollectiveView },
		],
	},
	{
		path: '/:collective',
		component: CollectiveView,
		props: (route) => route.params,
		children: [
			{ path: ':pageSlug-:pageId(\\d+)', component: CollectiveView },
			{ path: ':page(.*)', component: CollectiveView },
		],
	},
]

export default createRouter({
	history: createWebHistory(generateUrl('/apps/collectives')),
	routes,
})
