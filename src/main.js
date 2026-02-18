/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp } from 'vue'
import CollectivesApp from './CollectivesApp.vue'
import router from './router.js'
import registerServiceWorker from './util/registerServiceWorker.ts'

if ('serviceWorker' in navigator) {
	registerServiceWorker()
}

window.OCA.Collectives = {
	...window.OCA.Collectives,
	vueRouter: router,
}

const pinia = createPinia()

const app = createApp(CollectivesApp)
app.use(pinia)
app.use(router)
app.mount('#content')

// Expose the app during E2E tests
if (window.Cypress) {
	window.app = app
}

export default app
