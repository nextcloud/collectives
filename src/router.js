/**
 * @copyright Copyright (c) 2020 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
 *
 * @license GNU AGPL version 3 or any later version
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
import Start from './views/Start'
import Pages from './views/Pages'

Vue.use(VueRouter)

// We cannot predict the base
// as some servers have index.php in their urls.
// So we take the current path and drop everything after '/apps/wiki'.
const base = window.location.pathname.replace(/\/apps\/wiki\/.*/, '/apps/wiki/')

const routes = [
	{ path: '/', component: Start, props: false },
	{ path: '/:subdir*/:filename',
		component: Pages,
		props: (route) => ({
			...route.params,
			pageId: Number(route.query.fileId),
			openFileId: Number(route.query.openFile),
		}),
	},
]

export default new VueRouter({
	mode: 'history',
	base,
	routes,
})
