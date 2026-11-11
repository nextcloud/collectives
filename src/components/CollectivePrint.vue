<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation
		class="print-navigation"
		:aria-label="t('collectives', 'Pages to export or print')">
		<template #list>
			<SkeletonLoading v-if="loadingPages" type="items" />
			<CollectivePrintPageTree
				v-else
				:nodes="pageTree"
				:selectedIds
				:collective="currentCollective"
				@toggle="togglePage" />
		</template>
	</NcAppNavigation>
	<NcAppContent class="print-content">
		<div class="print-header">
			<span v-if="!loadingPages" class="print-header__count">
				{{ t('collectives', '{selected} of {total} pages selected', { selected: selectedPages.length, total: allPages.length }) }}
			</span>
			<NcButton
				class="print-header__button"
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
				<PagePrint :page @ready="pageReady(page.id)" />
			</div>
		</div>
	</NcAppContent>
</template>

<script lang="ts">
import type { PageInfo } from '../types.ts'
import type { IndexedPage, PageTreeNode } from '../util/pageTree.ts'

import { t } from '@nextcloud/l10n'
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
import { filterPrintPages, printPagesQuery } from '../util/printPages.ts'

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

	data() {
		return {
			loadingPages: true,
			// Until the content of the initially selected pages is loaded
			loadingContent: true,
			printing: false,
			selectedIds: new Set<number>(),
			// Pages that were selected at least once. They stay rendered (but hidden) when unselected.
			renderedIds: new Set<number>(),
			// Rendered pages whose content is not loaded yet
			waitingFor: new Set<number>(),
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
			return this.loadingPages || this.loadingContent
		},

		loadedCount(): { count: number, total: number } {
			return {
				count: this.renderedIds.size - this.waitingFor.size,
				total: this.renderedIds.size,
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
				&& !this.selectedPages.some((page) => this.waitingFor.has(page.id))
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
		if (this.waitingFor.size === 0) {
			// No page will report being ready
			this.loadingContent = false
		}
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
		togglePage(node: PageTreeNode<PageInfo>, checked: boolean) {
			const selectedIds = new Set(this.selectedIds)
			for (const id of subtreePageIds(node)) {
				if (checked) {
					selectedIds.add(id)
				} else {
					selectedIds.delete(id)
				}
			}
			this.setSelectedIds(selectedIds)
			this.updateRouteQuery()
		},

		setSelectedIds(selectedIds: Set<number>) {
			this.selectedIds = selectedIds
			for (const id of selectedIds) {
				if (!this.renderedIds.has(id)) {
					this.renderedIds.add(id)
					this.waitingFor.add(id)
				}
			}
		},

		/**
		 * Keep the selection in the URL, so it survives reloading the page
		 */
		updateRouteQuery() {
			const query = { ...this.$route.query }
			delete query.pages
			delete query.exclude
			Object.assign(query, printPagesQuery(this.allPages.map((page) => page.id), this.selectedIds))
			this.$router.replace({ query })
		},

		pageReady(pageId: number) {
			this.waitingFor.delete(pageId)
			if (this.waitingFor.size === 0) {
				this.loadingContent = false
			}
		},

		async print() {
			this.printing = true
			await this.waitForImages()
			this.printing = false
			await this.$nextTick()
			document.title = this.documentTitle
			window.print()
		},

		/**
		 * Wait until the images in the selected pages are loaded
		 *
		 * Waits 1 sec for each image (min 3 sec, max. 15 sec) and gives up afterwards.
		 */
		async waitForImages(): Promise<void> {
			const pagesEl = this.$refs.pagesEl as HTMLElement
			const loading = new Set(pagesEl.querySelectorAll('.print-page:not(.print-page--hidden) div.ProseMirror figure.image.icon-loading'))
			if (!loading.size) {
				return
			}

			const listeners = new AbortController()
			const imagesLoaded = new Promise<boolean>((resolve) => {
				const onLoad = (event: Event) => {
					if (!(event.target as HTMLElement).classList.contains('image__main')) {
						return
					}
					loading.delete(event.currentTarget as Element)
					if (!loading.size) {
						// Let the editor finish rendering the image
						setTimeout(() => resolve(true), 100)
					}
				}
				for (const el of loading) {
					// Hook into the capture phase as `load` events do not bubble up.
					el.addEventListener('load', onLoad, { capture: true, signal: listeners.signal })
				}
			})

			let timeoutId: ReturnType<typeof setTimeout> | undefined
			const timeout = new Promise<boolean>((resolve) => {
				timeoutId = setTimeout(() => resolve(false), Math.min(Math.max(loading.size * 1000, 3000), 15000))
			})

			const loaded = await Promise.race([imagesLoaded, timeout])
			clearTimeout(timeoutId)
			listeners.abort()
			if (!loaded) {
				console.error(`Failed to load ${loading.size} images`)
			}
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
