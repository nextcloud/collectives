/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp, watchEffect } from 'vue'
import CollectivesApp from './CollectivesApp.vue'
import { createOpenCollectivesLink } from './composables/useCollectivesLinkHandler.ts'
import router from './router.js'
import { useCollectivesStore } from './stores/collectives.js'
import { usePagesStore } from './stores/pages.js'
import registerServiceWorker from './util/registerServiceWorker.ts'

if ('serviceWorker' in navigator) {
	registerServiceWorker()
}

const pinia = createPinia()

window.OCA.Collectives = {
	...window.OCA.Collectives,
	vueRouter: router,
	openLink: createOpenCollectivesLink(router, pinia),
}

const app = createApp(CollectivesApp)
app.use(pinia)
app.use(router)
await router.isReady()
app.mount('#content')

const collectivesStore = useCollectivesStore(pinia)
const pagesStore = usePagesStore(pinia)

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
		storeIndex: pagesStore.collectiveIndex,
	}
})

// Expose the app during Cypress tests
if (window.Cypress) {
	window.app = app
}
export default app
