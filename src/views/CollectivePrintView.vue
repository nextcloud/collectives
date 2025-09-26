<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<CollectivePrint v-if="currentCollective" />
		<NcEmptyContent v-else-if="loading('collectives')" />
		<CollectiveNotFound v-else />
	</NcAppContent>
</template>

<script>

import { NcAppContent, NcEmptyContent } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'
import CollectivePrint from '../components/CollectivePrint.vue'
import { useCollectivesStore } from '../stores/collectives.js'
import { useRootStore } from '../stores/root.js'

export default {
	name: 'CollectivePrintView',

	components: {
		NcAppContent,
		CollectivePrint,
		CollectiveNotFound,
		NcEmptyContent,
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useCollectivesStore, ['currentCollective']),
	},

	mounted() {
		this.setPrintView()
	},

	methods: {
		...mapActions(useRootStore, ['setPrintView']),
	},
}
</script>

<style>
@media print {
	/* Shrink to body to prevent empty pages */
	body {
		height: fit-content;
	}

	#content-vue {
		position: static;
		overflow: visible;
		height: auto;
	}
}
</style>
