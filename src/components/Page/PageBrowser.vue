<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('collectives', 'Copy or move page')"
		size="normal"
		class="page-browser"
		@closing="onClose">
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
			<SkeletonLoading v-else-if="loading(`pagelist-${selectedCollective.id}`)" type="items" />
			<ul v-else-if="subpages.length > 0">
				<ListItem
					v-for="(page, index) in subpages"
					:id="page.id"
					:key="page.id"
					:emoji="page.emoji"
					:title="page.title"
					type="page"
					:currentId="pageId"
					@click="onClickPage(page)">
					<template #currentActions>
						<NcButton
							:disabled="index === 0"
							:aria-label="t('collectives', 'Move page up')"
							variant="tertiary"
							@click="onClickUp">
							<template #icon>
								<ArrowUpIcon :size="20" />
							</template>
						</NcButton>
						<NcButton
							:disabled="index === (subpages.length - 1)"
							:aria-label="t('collectives', 'Move page down')"
							variant="tertiary"
							@click="onClickDown">
							<template #icon>
								<ArrowDownIcon :size="20" />
							</template>
						</NcButton>
					</template>
				</ListItem>
			</ul>
		</div>
		<template #actions>
			<NcButton
				variant="secondary"
				:aria-label="t('collectives', copyPageString)"
				:disabled="isActionButtonsDisabled"
				@click="onMoveOrCopy(true)">
				<template #icon>
					<NcLoadingIcon v-if="isCopying" :size="20" />
				</template>
				{{ copyPageString }}
			</NcButton>
			<NcButton
				variant="primary"
				:aria-label="t('collectives', movePageString)"
				:disabled="isActionButtonsDisabled"
				@click="onMoveOrCopy(false)">
				<template #icon>
					<NcLoadingIcon v-if="isMoving" :size="20" />
				</template>
				{{ movePageString }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ArrowDownIcon from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUpIcon from 'vue-material-design-icons/ArrowUp.vue'
import SkeletonLoading from '../SkeletonLoading.vue'
import BreadCrumbs from './PageBrowser/BreadCrumbs.vue'
import ListItem from './PageBrowser/ListItem.vue'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'PageBrowser',

	components: {
		ArrowDownIcon,
		ArrowUpIcon,
		BreadCrumbs,
		ListItem,
		NcButton,
		NcDialog,
		NcLoadingIcon,
		SkeletonLoading,
	},

	props: {
		isCopying: {
			type: Boolean,
			default: false,
		},

		isMoving: {
			type: Boolean,
			default: false,
		},

		pageId: {
			type: Number,
			required: true,
		},

		parentId: {
			type: Number,
			required: true,
		},
	},

	emits: [
		'close',
		'copy',
		'move',
	],

	data() {
		return {
			selectedCollective: null,
			selectedPageId: null,
			reorderedSubpages: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useCollectivesStore, ['collectives', 'currentCollective']),
		...mapState(usePagesStore, [
			'rootPage',
			'pageById',
			'pageParents',
			'pageParentsForCollective',
			'pagesForCollective',
			'sortedSubpagesForCollective',
			'visibleSubpages',
		]),

		isActionButtonsDisabled() {
			return !this.selectedCollective || this.isCopying || this.isMoving
		},

		isCurrentCollective() {
			return this.selectedCollective?.id === this.currentCollective.id
		},

		selectedRootPage() {
			if (!this.selectedCollective) {
				return null
			}

			return this.isCurrentCollective
				? this.rootPage
				: this.pagesForCollective(this.selectedCollective).find((p) => (p.parentId === 0))
		},

		subpages() {
			if (!this.selectedCollective) {
				return []
			}

			// If we have reordered pages, return those for visual feedback
			if (this.reorderedSubpages) {
				return this.reorderedSubpages
			}

			let pages
			if (this.isCurrentCollective) {
				pages = this.visibleSubpages(this.selectedPageId)
			} else {
				pages = this.sortedSubpagesForCollective(this.selectedCollective, this.selectedPageId)
			}

			// Add current page to top of subpages if not part of it yet
			if (!pages.find((p) => (p.id === this.pageId))) {
				pages.unshift(this.pageById(this.pageId))
			}

			return pages
		},

		pageCrumbs() {
			if (!this.selectedCollective) {
				return []
			}

			return this.isCurrentCollective
				? this.pageParents(this.selectedPageId)
				: this.pageParentsForCollective(this.selectedCollective, this.selectedPageId)
		},

		movePageString() {
			return !this.selectedCollective || this.isCurrentCollective
				? t('collectives', 'Move page here')
				: t('collectives', 'Move page to {collective}', { collective: this.selectedCollective.name })
		},

		copyPageString() {
			return !this.selectedCollective || this.isCurrentCollective
				? t('collectives', 'Copy page here')
				: t('collectives', 'Copy page to {collective}', { collective: this.selectedCollective.name })
		},
	},

	watch: {
		selectedPageId: function(val) {
			if (val) {
				this.scrollToPage()
			}
		},
	},

	mounted() {
		this.selectedPageId = this.parentId
		this.selectedCollective = this.currentCollective
		this.scrollToPage()

		window.addEventListener('keydown', this.handleKeyDown, true)
	},

	beforeUnmount() {
		window.removeEventListener('keydown', this.handleKeyDown, true)
	},

	methods: {
		t,

		...mapActions(usePagesStore, ['getPagesForCollective']),

		scrollToPage() {
			// Scroll current page into view (important when listing parent page)
			this.$nextTick(() => {
				document.getElementById(`page-browser-page-${this.pageId}`).scrollIntoView({ block: 'center' })
			})
		},

		/**
		 *
		 * @param {number} from old index
		 * @param {number} to new index
		 */
		swapSubpages(from, to) {
			const length = this.subpages.length - 1
			if (from >= 0 && from <= length && to >= 0 && to <= length) {
				// Initialize reorderedSubpages if not already set
				if (!this.reorderedSubpages) {
					this.reorderedSubpages = [...this.subpages]
				}
				// Use splice to swap elements while maintaining reactivity
				this.reorderedSubpages.splice(from, 1, this.reorderedSubpages.splice(to, 1, this.reorderedSubpages[from])[0])
			}

			// Scroll current page into view
			this.$nextTick(() => {
				document.getElementById(`page-browser-page-${this.pageId}`).scrollIntoView({ block: 'center' })
			})
		},

		onClickCollectivesList() {
			this.selectedCollective = null
			this.selectedPageId = null
		},

		onClickCollectiveHome() {
			this.onClickCollective(this.selectedCollective)
		},

		/**
		 *
		 * @param {object} collective collective object
		 */
		async onClickCollective(collective) {
			this.selectedCollective = collective
			if (!this.isCurrentCollective) {
				await this.getPagesForCollective(this.selectedCollective)
			}
			this.selectedPageId = this.selectedRootPage.id
			// Reset reordered pages when changing collective
			this.reorderedSubpages = null
		},

		/**
		 *
		 * @param {object} page page object
		 */
		onClickPage(page) {
			if (page.id === this.pageId) {
				// Don't allow to move pages below themselves
				return
			}
			this.selectedPageId = page.id
			// Reset reordered pages when navigating
			this.reorderedSubpages = null
		},

		onClickDown() {
			const pageIndex = this.subpages.findIndex((p) => (p.id === this.pageId))
			this.swapSubpages(pageIndex, pageIndex + 1)
		},

		onClickUp() {
			const pageIndex = this.subpages.findIndex((p) => (p.id === this.pageId))
			this.swapSubpages(pageIndex, pageIndex - 1)
		},

		onClose() {
			this.$emit('close')
		},

		onMoveOrCopy(copy) {
			const args = {
				collectiveId: this.selectedCollective.id,
				parentId: this.selectedPageId,
				newIndex: this.subpages.findIndex((p) => p.id === this.pageId),
			}

			if (copy) {
				this.$emit('copy', args)
			} else {
				this.$emit('move', args)
			}
		},

		handleKeyDown(event) {
			if (event.key === 'ArrowDown') {
				event.preventDefault()
				this.onClickDown()
			}
			if (event.key === 'ArrowUp') {
				event.preventDefault()
				this.onClickUp()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.modal-container) {
	height: calc(100vw - 120px) !important;
	max-height: 500px !important;
}

.page-browser {
	display: flex;
	flex-direction: column;
}

.page-list {
	display: inline-block;
	width: 100%;
	height: calc(100% - 34px - 8px - 6px);
	overflow-y: auto;
	flex: 1;
}
</style>
