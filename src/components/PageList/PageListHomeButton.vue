<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="page-list-home">
		<NcAppNavigationItem
			:name="HOME_BUTTON_LABEL"
			:href="homeHref"
			:active="pagesStore.isLandingPage"
			@click="onClick">
			<template #icon>
				<HomeIcon v-if="pagesStore.isLandingPage" :size="20" />
				<HomeOutlineIcon v-else :size="20" />
			</template>
		</NcAppNavigationItem>
	</div>
</template>

<script setup lang="ts">
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import HomeOutlineIcon from 'vue-material-design-icons/HomeOutline.vue'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'

// TRANSLATORS Landing page of the current collective
const HOME_BUTTON_LABEL = t('collectives', 'Home')

const collectivesStore = useCollectivesStore()
const pagesStore = usePagesStore()
const router = useRouter()
const isMobile = useIsMobile()

// Full href for the landing page, so the entry is a real link
const homeHref = computed(() => router.resolve(collectivesStore.currentCollectivePath).href)

/**
 * Navigate to the landing page unless the user asked for a new tab or window via modifier key.
 *
 * @param event the native click event
 */
function onClick(event: MouseEvent) {
	if (event.metaKey || event.altKey || event.ctrlKey || event.shiftKey) {
		return
	}
	event.preventDefault()
	router.push(collectivesStore.currentCollectivePath)

	// Close the nav sidebar on mobile after navigating to the landing page
	if (isMobile.value) {
		emit('toggle-navigation', { open: false })
	}
}
</script>

<style lang="scss" scoped>
.page-list-home {
	padding-block-start: var(--default-grid-baseline);
	// Inline with the page list entries
	padding-inline: var(--default-grid-baseline);
}
</style>
