<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('collectives', 'Export or print')"
		data-cy-collectives="print-dialog"
		class="print-dialog"
		size="normal"
		@closing="onClose">
		<p class="hidden-visually" role="status">
			{{ loadingPages ? t('collectives', 'Loading pages') : '' }}
		</p>
		<SkeletonLoading v-if="loadingPages" aria-hidden="true" type="items" />
		<template v-else>
			<NcCheckboxRadioSwitch
				class="print-dialog__select-all"
				:modelValue="allSelected"
				:indeterminate="someSelected"
				@update:modelValue="toggleAll">
				{{ t('collectives', 'Select all pages') }}
			</NcCheckboxRadioSwitch>
			<CollectivePrintPageList
				class="print-dialog__page-list"
				:nodes="pageTree"
				:selectedIds
				:collective
				@toggle="togglePage" />
		</template>

		<template #actions>
			<NcButton
				variant="primary"
				:disabled="loadingPages || selectedIds.size === 0"
				@click="onPrint">
				<template #icon>
					<DownloadIcon :size="20" />
				</template>
				{{ t('collectives', 'Export or print') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { Collective, PageInfo } from '../../types.ts'
import type { PageTreeNode } from '../../util/pageTree.ts'

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { mapActions, mapState } from 'pinia'
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import SkeletonLoading from '../SkeletonLoading.vue'
import CollectivePrintPageList from './CollectivePrintPageList.vue'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import displayError from '../../util/displayError.js'
import { flattenPageTree } from '../../util/pageTree.ts'
import { printPagesQuery } from '../../util/printPages.ts'

export default defineComponent({
	name: 'CollectivePrintDialog',

	components: {
		CollectivePrintPageList,
		DownloadIcon,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		SkeletonLoading,
	},

	props: {
		collective: {
			type: Object as PropType<Collective>,
			required: true,
		},
	},

	data() {
		return {
			loadingPages: false,
			selectedIds: new Set<number>(),
		}
	},

	computed: {
		...mapState(useRootStore, ['isPublic']),
		...mapState(useCollectivesStore, ['collectivePrintPath']),
		...mapState(usePagesStore, [
			'pageTreeForCollective',
			'pagesForCollective',
			'rootPageForCollective',
		]),

		/**
		 * All pages of the collective as tree
		 */
		pageTree(): PageTreeNode<PageInfo>[] {
			const rootParentId = this.rootPageForCollective(this.collective)?.parentId ?? 0
			return this.pageTreeForCollective(this.collective, rootParentId)
		},

		pageIds(): number[] {
			return flattenPageTree(this.pageTree).map((page) => page.id)
		},

		allSelected(): boolean {
			return this.pageIds.length > 0 && this.selectedIds.size === this.pageIds.length
		},

		someSelected(): boolean {
			return this.selectedIds.size > 0 && !this.allSelected
		},
	},

	async mounted() {
		// Pages might not be loaded yet (other collective than the current one, or dialog opened on page load)
		if (this.pagesForCollective(this.collective).length === 0) {
			this.loadingPages = true
			await (this.isPublic ? this.getPages() : this.getPagesForCollective(this.collective))
				.catch(displayError('Could not fetch pages'))
			this.loadingPages = false
		}
		this.toggleAll(true)
	},

	methods: {
		t,

		...mapActions(useCollectivesStore, ['setPrintCollectiveId']),
		...mapActions(usePagesStore, ['getPages', 'getPagesForCollective']),

		toggleAll(checked: boolean) {
			this.selectedIds = new Set(checked ? this.pageIds : [])
		},

		togglePage(pageId: number, checked: boolean) {
			if (checked) {
				this.selectedIds.add(pageId)
			} else {
				this.selectedIds.delete(pageId)
			}
		},

		onPrint() {
			let url = generateUrl(`/apps/collectives${this.collectivePrintPath(this.collective)}`)
			// Not using URLSearchParams, as it would encode the commas
			const query = Object.entries(printPagesQuery(this.pageIds, this.selectedIds))
				.map(([key, value]) => `${key}=${value}`)
				.join('&')
			if (query) {
				url += `?${query}`
			}
			window.open(url, '_blank')
			this.onClose()
		},

		onClose() {
			this.setPrintCollectiveId(undefined)
		},
	},
})
</script>

<style scoped lang="scss">
.print-dialog__select-all {
	border-bottom: 1px solid var(--color-border);
	margin-bottom: 4px;
	padding-bottom: 4px;
}

.print-dialog__page-list {
	max-height: 50vh;
	overflow-y: auto;
}
</style>
