<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation
		class="print-navigation"
		:aria-label="t('collectives', 'Pages to export or print')">
		<div class="print-actions">
			<NcButton
				variant="primary"
				wide
				:disabled="!canPrint"
				@click="print">
				<template #icon>
					<NcLoadingIcon v-if="printing" :size="20" />
					<DownloadIcon v-else :size="20" />
				</template>
				{{ t('collectives', 'Export or print') }}
			</NcButton>
			<span v-if="!loadingPages" class="print-actions__count">
				{{ t('collectives', '{selected} of {total} pages selected', { selected: selectedPages.length, total: allPages.length }) }}
			</span>
		</div>
		<template #list>
			<SkeletonLoading v-if="loadingPages" type="items" />
			<CollectivePrintPageTree
				v-else
				:nodes="pageTree"
				:selectedIds
				:disabled="printing"
				:collective="currentCollective"
				@toggle="togglePage" />
		</template>
	</NcAppNavigation>
	<NcAppContent class="print-content">
		<div v-if="isMobile" class="print-header">
			<NcButton
				variant="primary"
				:disabled="!canPrint"
				@click="print">
				<template #icon>
					<NcLoadingIcon v-if="printing" :size="20" />
					<DownloadIcon v-else :size="20" />
				</template>
				{{ t('collectives', 'Export or print') }}
			</NcButton>
		</div>
		<NcEmptyContent
			v-show="loading"
			:name="t('collectives', 'Preparing collective for exporting or printing')">
			<template #icon>
				<DownloadIcon />
			</template>
			<template #action>
				<NcProgressBar :value="loadingProgress" size="medium" />
				<p v-if="loadedCount.total" class="load-messages">
					{{ t('collectives', 'Loading pages:') }}
					{{ `${loadedCount.count} / ${loadedCount.total}` }}
				</p>
			</template>
		</NcEmptyContent>
		<NcEmptyContent
			v-if="!loading && selectedPages.length === 0"
			:name="t('collectives', 'No pages to export or print')"
			:description="t('collectives', 'Select the pages to export or print in the page list.')">
			<template #icon>
				<DownloadIcon />
			</template>
		</NcEmptyContent>
		<div ref="pagesEl">
			<div
				v-for="page in renderedPages"
				v-show="!loading && selectedIds.has(page.id)"
				:key="page.id"
				class="print-page"
				:class="{ 'print-page--hidden': !selectedIds.has(page.id) }">
				<PagePrint :page @failed="failedIds.add(page.id)" @ready="pageReady(page.id)" />
			</div>
		</div>
	</NcAppContent>
</template>

<script lang="ts">
import type { PageInfo } from '../types.ts'
import type { IndexedPage, PageTreeNode } from '../util/pageTree.ts'

import { showError } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import { defineComponent } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import CollectivePrintPageTree from './Collective/CollectivePrintPageTree.vue'
import PagePrint from './PagePrint.vue'
import SkeletonLoading from './SkeletonLoading.vue'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import displayError from '../util/displayError.js'
import { flattenPageTree, subtreePageIds } from '../util/pageTree.ts'
import { filterPrintPages, printPagesQuery, updateRenderedPages } from '../util/printPages.ts'

export default defineComponent({
	name: 'CollectivePrint',

	components: {
		CollectivePrintPageTree,
		DownloadIcon,
		NcAppContent,
		NcAppNavigation,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcProgressBar,
		PagePrint,
		SkeletonLoading,
	},

	setup() {
		const isMobile = useIsMobile()
		return { isMobile }
	},

	data() {
		return {
			loadingPages: true,
			printing: false,
			selectedIds: new Set<number>(),
			// Selected pages, and unselected pages whose content is already loaded (kept rendered but hidden)
			renderedIds: new Set<number>(),
			// Rendered pages whose content is not loaded yet
			waitingFor: new Set<number>(),
			failedIds: new Set<number>(),
		}
	},

	computed: {
		...mapState(useCollectivesStore, ['currentCollective']),
		...mapState(usePagesStore, ['pageTreeForCollective', 'rootPage']),

		pageTree(): PageTreeNode<PageInfo>[] {
			return this.pageTreeForCollective(this.currentCollective, this.rootPage?.parentId ?? 0)
		},

		allPages(): IndexedPage<PageInfo>[] {
			return flattenPageTree(this.pageTree)
		},

		renderedPages(): IndexedPage<PageInfo>[] {
			return this.allPages.filter((page) => this.renderedIds.has(page.id))
		},

		selectedPages(): IndexedPage<PageInfo>[] {
			return this.allPages.filter((page) => this.selectedIds.has(page.id))
		},

		loading(): boolean {
			return this.loadingPages || this.loadedCount.count < this.loadedCount.total
		},

		loadedCount(): { count: number, total: number } {
			return {
				count: this.selectedPages.filter((page) => !this.waitingFor.has(page.id)).length,
				total: this.selectedPages.length,
			}
		},

		loadingProgress(): number {
			return this.loadedCount.total
				? this.loadedCount.count / this.loadedCount.total * 100
				: 0
		},

		canPrint(): boolean {
			return !this.loading
				&& !this.printing
				&& this.selectedPages.length > 0
		},

		documentTitle(): string {
			return [
				this.currentCollective.name,
				t('collectives', 'Collectives'),
				'Nextcloud',
			].join(' - ')
		},
	},

	async mounted() {
		await this.getPages()
			.catch(displayError('Could not fetch pages'))
		this.loadingPages = false
		const initialPages = filterPrintPages(this.allPages, this.$route.query)
		this.setSelectedIds(new Set(initialPages.map((page) => page.id)))
	},

	methods: {
		t,

		...mapActions(usePagesStore, ['getPages']),

		/**
		 * Select or unselect a page together with all its subpages
		 *
		 * @param node Page tree node of the page
		 * @param checked Whether to select the pages
		 */
		togglePage(node: PageTreeNode<PageInfo>, checked: boolean): void {
			// Change the reactive set in place, so only updates depending on the changed IDs are triggered
			for (const id of subtreePageIds(node)) {
				if (checked) {
					this.selectedIds.add(id)
				} else {
					this.selectedIds.delete(id)
				}
			}
			updateRenderedPages(this.selectedIds, this.renderedIds, this.waitingFor)
			this.updateRouteQuery()
		},

		setSelectedIds(selectedIds: Set<number>): void {
			this.selectedIds = selectedIds
			updateRenderedPages(this.selectedIds, this.renderedIds, this.waitingFor)
		},

		/**
		 * Keep the selection in the URL, so it survives reloading the page
		 */
		updateRouteQuery(): void {
			const query = { ...this.$route.query }
			delete query.pages
			delete query.exclude
			Object.assign(query, printPagesQuery(this.allPages.map((page) => page.id), this.selectedIds))
			this.$router.replace({ query })
		},

		pageReady(pageId: number): void {
			this.waitingFor.delete(pageId)
		},

		async print(): Promise<void> {
			this.printing = true
			try {
				await this.waitForImages()
			} finally {
				this.printing = false
			}
			const failedCount = this.selectedPages.filter((page) => this.failedIds.has(page.id)).length
			if (failedCount) {
				showError(n('collectives', 'Could not load the content of %n page', 'Could not load the content of %n pages', failedCount))
			}
			await this.$nextTick()
			document.title = this.documentTitle
			window.print()
		},

		/**
		 * Wait until the images in the selected pages are loaded or failed to load
		 *
		 * Waits 1 sec for each image (min 3 sec, max. 15 sec) and gives up afterwards.
		 */
		async waitForImages(): Promise<void> {
			const pagesEl = this.$refs.pagesEl as HTMLElement
			const pages = '.print-page:not(.print-page--hidden) div.ProseMirror'
			const loadingCount = () => pagesEl.querySelectorAll(`${pages} figure.image.icon-loading`).length
			const count = loadingCount()
			if (!count) {
				return
			}

			// Text removes `icon-loading` once an image is loaded or failed to load
			let resolveLoaded: (loaded: boolean) => void = () => {}
			const observer = new MutationObserver(() => {
				if (!loadingCount()) {
					resolveLoaded(true)
				}
			})
			observer.observe(pagesEl, { subtree: true, attributeFilter: ['class'] })
			let timeoutId: ReturnType<typeof setTimeout> | undefined
			const loaded = await new Promise<boolean>((resolve) => {
				resolveLoaded = resolve
				timeoutId = setTimeout(() => resolve(false), Math.min(Math.max(count * 1000, 3000), 15000))
			})
			clearTimeout(timeoutId)
			observer.disconnect()
			if (!loaded) {
				console.error(`Timed out loading ${loadingCount()} images`)
			}

			// Wait until the loaded images can be painted
			const images = pagesEl.querySelectorAll<HTMLImageElement>(`${pages} figure.image:not(.icon-loading) img.image__main`)
			await Promise.allSettled([...images].map((img) => img.decode()))
		},
	},
})
</script>

<style scoped lang="scss">
.print-header {
	position: sticky;
	top: 0;
	z-index: 1;
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 12px;
	min-height: var(--default-clickable-area);
	padding-block: var(--app-navigation-padding, 8px);
	// Leave room for the navigation toggle button
	padding-inline: calc(var(--default-clickable-area) + 2 * var(--app-navigation-padding, 8px)) var(--app-navigation-padding, 8px);
	background-color: var(--color-main-background);

}

// Keep the actions above the page list fully visible, only the page list scrolls
.print-navigation :deep(.app-navigation__body) {
	flex-shrink: 0;
	overflow: visible;
}

.print-actions {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: var(--default-grid-baseline);
	padding: var(--app-navigation-padding, 8px) calc(var(--app-navigation-padding, 8px) + var(--default-grid-baseline)) 0;

	&__count {
		color: var(--color-text-maxcontrast);
	}
}

.progress-bar {
	margin-top: 8px;
}

.load-messages {
	color: var(--color-text-maxcontrast);
}

@media print {
	.print-navigation,
	.print-header {
		display: none !important;
	}
}
</style>
