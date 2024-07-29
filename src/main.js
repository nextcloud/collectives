/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
