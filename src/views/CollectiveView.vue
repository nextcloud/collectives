<template>
	<NcAppContent :show-details="showing('details')"
		:list-size="20"
		:list-min-width="15"
		@update:showDetails="hide('details')">
		<template #list>
			<PageList v-if="currentCollective" />
		</template>
		<Collective v-if="currentCollective" />
		<NcEmptyContent v-else-if="loading('collectives')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>
		<CollectiveNotFound v-else />
	</NcAppContent>
</template>

<script>

import { mapGetters, mapMutations } from 'vuex'
import { showWarning } from '@nextcloud/dialogs'
import { NcAppContent, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'

import Collective from '../components/Collective.vue'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'
import PageList from '../components/PageList.vue'

export default {
	name: 'CollectiveView',

	components: {
		Collective,
		CollectiveNotFound,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		PageList,
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentPage',
			'loading',
			'pagePrintLink',
			'showing',
		]),
	},

	mounted() {
		window.addEventListener('keydown', this.printKeyHandler)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.printKeyHandler)
	},

	methods: {
		...mapMutations(['hide']),

		/**
		 * @param {KeyboardEvent} event the keydown event
		 */
		printKeyHandler(event) {
			// Handle `CTRL+P` or `CMD+P` but ensure ALT or SHIFT are NOT pressed (e.g. CTRL+SHIFT+P is new private tab on firefox)
			if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'p' && !(event.altKey || event.shiftKey)) {
				if (!this.currentPage) return

				const handle = window.open(this.pagePrintLink(this.currentPage), 'ncCollectivesPrint')
				if (handle === null) {
					// This might happen because of popup blockers etc
					showWarning(t('collectives', 'Could not open print view, try to disable any popup blockers.'))
				} else {
					handle.focus()
					event.preventDefault()
					event.stopImmediatePropagation()
				}
			}
		},
	},
}
</script>

<style>
/* Align details toggle button with page title bar (only relevant on mobile) */
button.app-details-toggle {
	z-index: 10023 !important;
	top: 61px !important;
	position: fixed !important;
}
</style>
