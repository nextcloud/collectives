/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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
import App from './App'
import Path from './Path'

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(VueRouter)

// Define some routes
// Each route should map to a component. The "component" can
// either be an actual component constructor created via
// `Vue.extend()`, or just a component options object.
// We'll talk about nested routes later.
const routes = [
	{ path: '/:name', component: Path, props: true },
]

// We cannot predict the base
// as some servers have index.php in their urls.
// So we take the current path and drop everything after '/apps/wiki'.
const base = window.location.pathname.replace(/\/apps\/wiki\/.*/, '/apps/wiki/')

const router = new VueRouter({
	mode: 'history',
	base,
	routes,
})

export default new Vue({
	router,
	el: '#content',
	render: h => h(App),
})
