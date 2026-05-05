/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp, watchEffect } from 'vue'
import CollectivesApp from './CollectivesApp.vue'
import router from './router.js'
import { useCollectivesStore } from './stores/collectives.js'
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
await router.isReady()
app.mount('#content')

const collectivesStore = useCollectivesStore(pinia)

watchEffect(() => {
	const collective = collectivesStore.currentCollective
	if (!collective) {
		delete window.OCA.Collectives.currentCollective
		return
	}
	window.OCA.Collectives.currentCollective = {
		id: collective.id,
		nameWithEmoji: collective.emoji
			? collective.emoji + ' ' + collective.name
			: collective.name,
		path: collectivesStore.currentCollectivePath,
	}
})

// Expose the app during Cypress tests
if (window.Cypress) {
	window.app = app
}
export default app
