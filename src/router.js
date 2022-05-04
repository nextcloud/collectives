/**
 * @copyright Copyright (c) 2020 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import Vue from 'vue'
import VueRouter from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import Home from './views/Home'
import CollectivePrintView from './views/CollectivePrintView'
import CollectiveView from './views/CollectiveView'

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
