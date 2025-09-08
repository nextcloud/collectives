/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import Collectives from './Collectives.vue'
import router from './router.js'
import { createPinia, PiniaVuePlugin } from 'pinia'

window.OCA.Collectives = {
	...window.OCA.Collectives,
	vueRouter: router,
}

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(PiniaVuePlugin)
const pinia = createPinia()

const app = new Vue({
	el: '#content',
	router,
	pinia,
	render: h => h(Collectives),
})

// Expose the app during E2E tests
if (window.Cypress) {
	window.app = app
}

export default app
