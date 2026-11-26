<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="publish-page-selection">
		<div class="publish-page-selection__toolbar">
			<NcTextField
				v-model="filter"
				class="publish-page-selection__filter"
				:label="t('collectives', 'Filter pages')"
				:showTrailingButton="filter !== ''"
				:trailingButtonLabel="t('collectives', 'Clear filter')"
				@trailingButtonClick="filter = ''">
				<template #icon>
					<MagnifyIcon :size="20" />
				</template>
			</NcTextField>
			<div class="publish-page-selection__select-buttons">
				<NcButton @click="selectAll">
					{{ t('collectives', 'Select all') }}
				</NcButton>
				<NcButton @click="selectNone">
					{{ t('collectives', 'Select none') }}
				</NcButton>
			</div>
		</div>
		<ul class="publish-page-selection__tree">
			<li v-if="rootPage" class="publish-page-selection__home-row">
				<PublishTreeRow selected disabled>
					<template #icon>
						<HomeIcon :size="20" fillColor="var(--color-text-maxcontrast)" />
					</template>
					{{ t('collectives', 'Home') }}
					<template #details>
						{{ t('collectives', 'The home page is always part of the website.') }}
					</template>
				</PublishTreeRow>
			</li>
			<PublishPageTreeItem
				v-for="page in topLevelPages"
				:key="page.id"
				:collective="collective"
				:page="page"
				:selectedPageIds="selectedPageIds"
				:expandedPageIds="expandedPageIds"
				:filter="filter.trim()"
				:filteredPageIds="filteredPageIds"
				@toggleSelect="onToggleSelect"
				@toggleExpand="onToggleExpand" />
			<li v-if="filteredPageIds && !topLevelPages.length" class="publish-page-selection__no-match">
				{{ t('collectives', 'No pages match the filter.') }}
			</li>
		</ul>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import PublishPageTreeItem from './PublishPageTreeItem.vue'
import PublishTreeRow from './PublishTreeRow.vue'
import { usePagesStore } from '../../stores/pages.js'
import { pageIdsMatchingFilter } from '../../util/staticSitePageFilter.js'

export default {
	name: 'PublishPageSelection',

	components: {
		HomeIcon,
		MagnifyIcon,
		NcButton,
		NcTextField,
		PublishPageTreeItem,
		PublishTreeRow,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},

		/** @type {Set<number>} */
		selectedPageIds: {
			type: Set,
			required: true,
		},
	},

	emits: [
		'update:selectedPageIds',
	],

	data() {
		return {
			expandedPageIds: new Set(),
			filter: '',
		}
	},

	computed: {
		...mapState(usePagesStore, [
			'pageAncestorIdsForCollective',
			'pagesForCollective',
			'pagesTreeWalkForCollective',
			'visibleSubpagesForCollective',
		]),

		allPageIds() {
			return this.pagesTreeWalkForCollective(this.collective).map((page) => page.id)
		},

		rootPage() {
			return this.pagesForCollective(this.collective).find((page) => page.parentId === 0)
		},

		// Matching pages and their ancestors while filtering, null otherwise
		filteredPageIds() {
			return pageIdsMatchingFilter(this.pagesForCollective(this.collective), this.filter)
		},

		topLevelPages() {
			if (!this.rootPage) {
				return []
			}
			const pages = this.visibleSubpagesForCollective(this.collective, this.rootPage.id)
			return this.filteredPageIds
				? pages.filter((page) => this.filteredPageIds.has(page.id))
				: pages
		},
	},

	methods: {
		t,

		// Select all and none always affect all pages, also while filtering
		selectAll() {
			this.$emit('update:selectedPageIds', new Set(this.allPageIds))
		},

		// The home page is always part of the website
		selectNone() {
			this.$emit('update:selectedPageIds', new Set(this.rootPage ? [this.rootPage.id] : []))
		},

		onToggleSelect(pageId) {
			// Toggling a page also selects/deselects all of its subpages
			const select = !this.selectedPageIds.has(pageId)
			const affectedIds = [
				pageId,
				...this.pagesTreeWalkForCollective(this.collective, pageId).map((page) => page.id),
			]

			const selectedPageIds = new Set(this.selectedPageIds)
			for (const id of affectedIds) {
				if (select) {
					selectedPageIds.add(id)
				} else {
					selectedPageIds.delete(id)
				}
			}

			// Selecting a page also selects its ancestor pages, so a subpage is never
			// published without the pages it is nested in
			if (select) {
				for (const ancestorId of this.pageAncestorIdsForCollective(this.collective, pageId)) {
					selectedPageIds.add(ancestorId)
				}
			}

			this.$emit('update:selectedPageIds', selectedPageIds)
		},

		onToggleExpand(pageId) {
			const expandedPageIds = new Set(this.expandedPageIds)
			if (expandedPageIds.has(pageId)) {
				expandedPageIds.delete(pageId)
			} else {
				expandedPageIds.add(pageId)
			}
			this.expandedPageIds = expandedPageIds
		},
	},
}
</script>

<style lang="scss" scoped>
.publish-page-selection {
	container-type: inline-size;
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	min-height: 0;
	margin-block-start: 12px;

	// The text field has a margin on top for its label, so the buttons are aligned to its bottom
	&__toolbar {
		display: flex;
		align-items: flex-end;
		gap: 8px;
	}

	&__filter {
		flex: 1 1 auto;
	}

	&__select-buttons {
		display: flex;
		gap: 8px;
		flex: 0 0 auto;
	}

	@container (width < 450px) {
		&__toolbar {
			flex-wrap: wrap;
		}

		&__select-buttons {
			margin-inline-start: auto;
		}
	}

	&__tree {
		flex: 1 1 auto;
		margin-block-start: 16px;
		margin-block-end: 24px;
		padding: 8px;
		overflow-y: auto;
		border: 1px solid var(--color-border-dark);
		border-radius: var(--border-radius-large);
	}

	&__home-row {
		list-style: none;
	}

	&__no-match {
		list-style: none;
		padding: 8px;
		color: var(--color-text-maxcontrast);
	}
}
</style>
