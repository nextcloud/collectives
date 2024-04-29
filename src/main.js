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
import Collectives from './Collectives.vue'
import router from './router.js'
import store from './store/store.js'
import { sync } from 'vuex-router-sync'

import './shared-init.js'

window.OCA.Collectives = {
	...window.OCA.Collectives,
	vueRouter: router,
}

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

sync(store, router)

const app = new Vue({
	el: '#content',
	router,
	store,
	render: h => h(Collectives),
})

// Expose the app during E2E tests
if (window.Cypress) {
	window.app = app
}

export default app
