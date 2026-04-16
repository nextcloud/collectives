/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp, watch } from 'vue'
import CollectivesApp from './CollectivesApp.vue'
import router from './router.js'
import { useCollectivesStore } from './stores/collectives.js'
import { useRootStore } from './stores/root.js'
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

const rootStore = useRootStore(pinia)
const collectivesStore = useCollectivesStore(pinia)

watch(() => rootStore.collectiveId, (id) => {
	window.OCA.Collectives.currentCollectiveId = id
	window.OCA.Collectives.currentCollectivePath = collectivesStore.currentCollectivePath
}, { immediate: true })

// Expose the app during Cypress tests
if (window.Cypress) {
	window.app = app
}
export default app
