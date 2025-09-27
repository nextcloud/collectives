import { generateUrl } from '@nextcloud/router'
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import Vue from 'vue'
import VueRouter from 'vue-router'
import CollectivePrintView from './views/CollectivePrintView.vue'
import CollectiveView from './views/CollectiveView.vue'
import HomeView from './views/HomeView.vue'

Vue.use(VueRouter)

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
			{ path: ':pageSlug-:pageId(\\d+)' },
			{ path: ':page*' },
		],
	},
	{
		path: '/p/:token/:collective',
		component: CollectiveView,
		props: (route) => route.params,
		children: [
			{ path: ':pageSlug-:pageId(\\d+)' },
			{ path: ':page*' },
		],
	},
	{
		path: '/:collectiveSlug-:collectiveId(\\d+)',
		component: CollectiveView,
		props: (route) => route.params,
		children: [
			{ path: ':pageSlug-:pageId(\\d+)' },
			{ path: ':page*' },
		],
	},
	{
		path: '/:collective',
		component: CollectiveView,
		props: (route) => route.params,
		children: [
			{ path: ':pageSlug-:pageId(\\d+)' },
			{ path: ':page*' },
		],
	},
]

export default new VueRouter({
	mode: 'history',
	base: generateUrl('/apps/collectives', ''),
	routes,
})
