<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="modal-scroller">
		<h2>{{ t('collectives', 'Collectives pages') }}</h2>
		<div class="modal-inner">
			<NcTextField
				v-model="query"
				:label="t('collectives', 'Search pages…')"
				:showTrailingButton="!!query"
				:trailingButtonLabel="t('collectives', 'Clear search')"
				@trailingButtonClick="query = ''" />

			<BreadCrumbs
				:selectedCollective
				:pageCrumbs
				:rootPage
				@clickCollectivesList="onClickCollectivesList"
				@clickCollectiveHome="onClickCollectiveHome"
				@clickPage="onClickPage" />
			<div class="page-list">
				<ul v-if="!selectedCollective">
					<ListItem
						v-for="collective in collectives"
						:id="collective.id"
						:key="collective.id"
						:emoji="collective.emoji"
						:title="collective.name"
						type="collective"
						@click="onClickCollective(collective)" />
				</ul>
				<!-- TODO skeleton loading? -->
				<ul v-else-if="subpages.length > 0">
					<ListItem
						v-for="page in subpages"
						:id="page.id"
						:key="page.id"
						:emoji="page.emoji"
						:title="page.title"
						type="page"
						@click="onClickPage(page)" />
				</ul>
			</div>

			<!-- TODO
			<NcEmptyContent>
				<template #icon>
					<CollectivesIcon />
				</template>
			</NcEmptyContent>
			-->

			<div class="modal-buttons">
				<NcButton @click="close">
					{{ t('collectives', 'Cancel') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script lang="ts">
import type { Collective, PageInfo } from '../types.ts'

import { t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'
import BreadCrumbs from '../components/Page/PageBrowser/BreadCrumbs.vue'
import ListItem from '../components/Page/PageBrowser/ListItem.vue'
import { getCollectives, getPages } from '../apis/collectives/index.js'
import { byOrder } from '../util/sortOrders.js'

export default defineComponent({
	name: 'PagePicker',

	components: {
		ListItem,
		BreadCrumbs,
		CollectivesIcon,
		NcButton,
		NcEmptyContent,
		NcTextField,
	},

	emits: [
		'cancel',
		'submit',
	],

	setup() {
		return { t }
	},

	data() {
		return {
			collectives: [] as Collective[],
			selectedCollective: null as Collective | null,
			allPages: [] as PageInfo[],
			rootPage: null as PageInfo | null,
			selectedPageId: null as number | null,
			isLoadingCollectives: false,
			isLoadingPages: false,
			query: '',
		}
	},

	computed: {
		pageCrumbs(): PageInfo[] {
			if (!this.selectedCollective) {
				return []
			}

			// TODO
			return []
		},

		subpages(): PageInfo[] {
			if (!this.selectedPageId) {
				return []
			}
			const parentPage = this.allPages.find((page) => page.id === this.selectedPageId)
			const customOrder = parentPage?.subpageOrder || []
			return this.allPages
				.filter((p) => p.parentId = this.selectedPageId)
				.map((p) => ({ ...p, index: customOrder.indexOf(p.id) }))
				.sort(byOrder)
			return []
		},
	},

	async mounted() {
		this.isLoadingCollectives = true
		try {
			const response = await getCollectives()
			this.collectives = response.data.ocs.data.collectives
		} finally {
			this.isLoadingCollectives = false
		}
	},

	methods: {
		close() {
			this.$emit('cancel')
		},

		async onClickCollective(collective: Collective) {
			this.selectedCollective = collective
			this.selectedPageId = null
			this.allPages = []
			this.rootPage = null
			this.isLoadingPages = true
			try {
				// TODO public
				const context = { isPublic: false, collectiveId: collective.id, shareTokenparam: null }
				const response = await getPages(context)
				this.allPages = response.data.ocs.data.pages
				this.rootPage = this.allPages[0]
				this.selectedPageId = this.rootPage.id ?? null
			} finally {
				this.isLoadingPages = false
			}
		},

		onClickCollectivesList() {
			this.selectedCollective = null
			this.allPages = []
			this.rootPage = null
			this.selectedPageId = null
			this.query = ''
		},

		onClickCollectiveHome() {
			// TODO
		},

		onClickPage(page: PageInfo) {
			// TODO
		},
	},
})
</script>

<style lang="scss" scoped>
.modal-scroller {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	h2 {
		display: flex;
		margin: 12px 0 20px;
	}
}

.modal-inner {
	box-sizing: border-box;
	display: flex;
	flex-direction: column;
	width: 100%;
	min-height: 400px;
	padding: calc(var(--default-grid-baseline) * 3);
}

.modal-buttons {
	display: flex;
	justify-content: flex-end;
	position: sticky;
	bottom: 0;
}

.page-list {
	display: inline-block;
	width: 100%;
	height: calc(100% - 34px - 8px - 6px);
	overflow-y: auto;
	flex: 1;
}
</style>
