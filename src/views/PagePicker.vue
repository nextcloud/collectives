<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="modal-scroller">
		<h2>{{ t('collectives', 'Add link to page') }}</h2>
		<div class="modal-inner">
			<div class="searchbar">
				<NcTextField
					ref="searchInput"
					v-model="query"
					:label="t('collectives', 'Search pages…')"
					:showTrailingButton="!!query"
					:trailingButtonLabel="t('collectives', 'Clear search')"
					@trailingButtonClick="query = ''" />
				<NcActions v-if="collectiveId">
					<template #icon>
						<FilterCheckOutlineIcon v-if="filterCollective" :size="20" />
						<FilterOutlineIcon v-else :size="20" />
					</template>
					<NcActionCheckbox v-model="filterCollective">
						{{ t('collectives', 'Limit to current collective') }}
					</NcActionCheckbox>
					<NcActionText>
						<template #icon>
							<AlertOutlineIcon :size="20" />
						</template>
						{{ t('collectives', 'Links to pages from other collectives might not be accessible to everyone in this collective.') }}
					</NcActionText>
				</NcActions>
			</div>

			<div class="page-list">
				<ul v-if="pages.length > 0">
					<PagePreview
						v-for="page in pages"
						:key="page.id"
						:title="page.title"
						:description="description(page)"
						:emoji="page.emoji!"
						:lastUserId="page.lastUserId!"
						:lastUserDisplayName="page.lastUserDisplayName!"
						:small="true"
						class="page-preview-item"
						@click="onClickPage(page)" />
				</ul>
				<NcEmptyContent
					v-else-if="searchedPagesLoading"
					:description="t('collectives', 'Loading pages')">
					<template #icon>
						<NcLoadingIcon />
					</template>
				</NcEmptyContent>
				<NcEmptyContent v-else :description="t('collectives', 'No pages to link')">
					<template #icon>
						<CollectivesIcon />
					</template>
				</NcEmptyContent>
			</div>

			<!-- TODO
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
import type { PageInfo } from '../types.ts'

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { defineComponent } from 'vue'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionText from '@nextcloud/vue/components/NcActionText'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AlertOutlineIcon from 'vue-material-design-icons/AlertOutline.vue'
import FilterCheckOutlineIcon from 'vue-material-design-icons/FilterCheckOutline.vue'
import FilterOutlineIcon from 'vue-material-design-icons/FilterOutline.vue'
import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'
import PagePreview from '../components/PagePreview.vue'
import { getRecentPages, searchPages } from '../apis/collectives/index.js'
import { byTimeAsc } from '../util/sortOrders.js'

export default defineComponent({
	name: 'PagePicker',

	components: {
		AlertOutlineIcon,
		CollectivesIcon,
		FilterCheckOutlineIcon,
		FilterOutlineIcon,
		NcActionCheckbox,
		NcActions,
		NcActionText,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcTextField,
		PagePreview,
	},

	emits: [
		'cancel',
	],

	setup() {
		return { t }
	},

	data() {
		return {
			collectiveId: null,
			filterCollective: false,
			currentPages: [] as PageInfo[],
			searchedPages: [] as PageInfo[],
			searchedPagesLoading: false,
			rootPage: null as PageInfo | null,
			selectedPageId: null as number | null,
			isLoadingPages: false,
			query: '',
			debouncedGetSearchedPages: debounce(this.getSearchedPages, 300),
		}
	},

	computed: {
		currentRecentPages() {
			const recentPages = this.currentPages
				.slice()
				.sort(byTimeAsc)
			return this.query
				? recentPages.filter((p) => p.title.toLocaleLowerCase().includes(this.query.toLowerCase()))
				: recentPages
		},

		pages() {
			return this.filterCollective
				? this.currentRecentPages.slice(0, 10)
				: this.searchedPages.slice(0, 10)
		},
	},

	watch: {
		query() {
			if (!this.filterCollective) {
				this.searchedPages = []
				this.debouncedGetSearchedPages()
			}
		},

		filterCollective(newValue) {
			if (!newValue) {
				this.getSearchedPages()
			}
		},
	},

	async mounted() {
		this.collectiveId = window.OCA?.Collectives?.currentCollectiveId
		if (this.collectiveId) {
			this.filterCollective = true
			const raw = localStorage.getItem('collectives/pinia/pages/allPages')
			if (raw) {
				const allPagesMap = JSON.parse(raw)
				this.currentPages = allPagesMap[this.collectiveId] ?? []
			}
		} else {
			// TODO: only do if not in public share
			this.getSearchedPages()
		}
		// TODO: doesn't work
		this.$refs.searchInput.focus()
	},

	methods: {
		close() {
			this.$emit('cancel')
		},

		onClickPage(page: PageInfo) {
			if (window.OCA.Collectives?.currentCollectivePath) {
				const pageLink = window.location.origin
					+ generateUrl('/apps/collectives')
					+ window.OCA.Collectives.currentCollectivePath
					+ '/' + page.slug + '-' + page.id
				this.$el.dispatchEvent(new CustomEvent('submit', {
					bubbles: true,
					detail: pageLink,
				}))
			} else {
				console.error('Cannot generate page link from outside Collectives app')
			}
		},

		description(page: PageInfo) {
			const collectiveNameWithEmoji = this.filterCollective
				? window.OCA.Collectives?.currentCollectiveNameWithEmoji || ''
				: page.collectiveNameWithEmoji

			const collectiveAndPagePath = page.filePathString
				? collectiveNameWithEmoji + ' - ' + page.filePathString
				: collectiveNameWithEmoji
			return t('collectives', 'In collective {path}', { path: collectiveAndPagePath })
		},

		async getSearchedPages() {
			this.searchedPagesLoading = true
			try {
				if (this.query === '') {
					// Get recent pages when query is empty
					const response = await getRecentPages()
					this.searchedPages = response.data.ocs.data.pages
				} else {
					// Search pages when query is not empty
					const response = await searchPages(this.query)
					this.searchedPages = response.data.ocs.data.pages
				}
			} finally {
				this.searchedPagesLoading = false
			}
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
		margin-top: 12px;
	}
}

.modal-inner {
	box-sizing: border-box;
	display: flex;
	gap: calc(3 * var(--default-grid-baseline));
	flex-direction: column;
	width: 100%;
	height: 500px;
	padding: calc(var(--default-grid-baseline) * 3);
}

.modal-buttons {
	display: flex;
	justify-content: flex-end;
	position: sticky;
	bottom: 0;
}

.searchbar {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
}

.page-list {
	display: inline-block;
	width: 100%;
	height: calc(100% - 34px - 8px - 6px);
	overflow-y: auto;
	flex: 1;

	ul {
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
	}
}

.page-preview-item {
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-container);
	margin-inline-end: calc(3 * var(--default-grid-baseline));
	width: unset;
}
</style>
