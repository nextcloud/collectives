/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import Vue from 'vue'
import VueRouter from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import Home from './views/Home.vue'
import CollectivePrintView from './views/CollectivePrintView.vue'
import CollectiveView from './views/CollectiveView.vue'

Vue.use(VueRouter)

const routes = [
	{
		path: '/',
		component: Home,
	},
	{
		path: '/_/print/:collective',
		component: CollectivePrintView,
		props: (route) => route.params,
	},
	{
		path: '/p/:token/print/:collective',
		component: CollectivePrintView,
		props: (route) => route.params,
	},
	{
		path: '/p/:token/:collective',
		component: CollectiveView,
		props: (route) => route.params,
		children: [{ path: ':page*' }],
	},
	{
		path: '/:collective',
		component: CollectiveView,
		props: (route) => route.params,
		children: [{ path: ':page*' }],
	},
]

export default new VueRouter({
	mode: 'history',
	base: generateUrl('/apps/collectives', ''),
	routes,
})
