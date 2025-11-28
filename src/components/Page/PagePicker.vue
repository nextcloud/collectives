<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('collectives', 'Copy or move page')"
		size="normal"
		class="page-picker"
		@closing="onClose">
		<span class="crumbs">
			<div v-if="!selectedCollective || !selectedCollective.isPageShare" class="crumbs-home">
				<NcButton
					variant="tertiary"
					:aria-label="t('collectives', 'Breadcrumb for list of collectives')"
					:disabled="!selectedCollective"
					class="crumb-button home"
					@click="onClickCollectivesList">
					<template #icon>
						<CollectivesIcon :size="20" />
					</template>
					{{ collectivesCrumbString }}
				</NcButton>
				<ChevronRightIcon :size="20" />
			</div>
			<template v-if="selectedCollective">
				<div class="crumbs-level">
					<NcButton
						variant="tertiary"
						:aria-label="collectiveBreadcrumbAriaLabel"
						:disabled="pageCrumbs.length === 0"
						class="crumb-button"
						@click="onClickCollectiveHome">
						<template v-if="collectiveBreadcrumbEmoji" #icon>
							{{ collectiveBreadcrumbEmoji }}
						</template>
						{{ collectiveBreadcrumbTitle }}
					</NcButton>
				</div>
				<div
					v-for="(page, index) in pageCrumbs"
					:key="page.id"
					class="crumbs-level">
					<ChevronRightIcon :size="20" />
					<NcButton
						variant="tertiary"
						:aria-label="t('collectives', 'Breadcrumb, navigate to page {page}', { page: page.title })"
						:disabled="(index + 1) === pageCrumbs.length"
						class="crumb-button"
						@click="onClickPage(page)">
						{{ page.title }}
					</NcButton>
				</div>
			</template>
		</span>
		<div class="picker-list">
			<ul v-if="!selectedCollective">
				<li
					v-for="collective in collectives"
					:id="`picker-collective-${collective.id}`"
					:key="collective.id">
					<a href="#" class="picker-item" @click="onClickCollective(collective)">
						<div v-if="collective.emoji" class="picker-icon">
							{{ collective.emoji }}
						</div>
						<CollectivesIcon
							v-else
							class="picker-icon"
							:size="20" />
						<div class="picker-title">
							{{ collective.name }}
						</div>
					</a>
				</li>
			</ul>
			<SkeletonLoading v-else-if="loading(`pagelist-${selectedCollective.id}`)" type="items" />
			<ul v-else-if="subpages.length > 0">
				<li
					v-for="(page, index) in subpages"
					:id="`picker-page-${page.id}`"
					:key="page.id">
					<a
						:class="{ self: page.id === pageId }"
						:href="page.id === pageId ? false : '#'"
						class="picker-item"
						@click="onClickPage(page)">
						<div v-if="page.emoji" class="picker-icon">
							{{ page.emoji }}
						</div>
						<PageIcon
							v-else
							class="picker-icon"
							:size="20"
							fill-color="var(--color-background-darker)" />
						<div class="picker-title">
							{{ page.title }}
						</div>
						<div v-if="page.id === pageId" class="picker-move-buttons">
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
						</div>
					</a>
				</li>
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
import { NcButton, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import ArrowDownIcon from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUpIcon from 'vue-material-design-icons/ArrowUp.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import PageIcon from '../Icon/PageIcon.vue'
import SkeletonLoading from '../SkeletonLoading.vue'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'PagePicker',

	components: {
		ArrowDownIcon,
		ArrowUpIcon,
		ChevronRightIcon,
		CollectivesIcon,
		NcButton,
		NcDialog,
		NcLoadingIcon,
		PageIcon,
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
			return this.isCurrentCollective
				? this.pageParents(this.selectedPageId)
				: this.pageParentsForCollective(this.selectedCollective, this.selectedPageId)
		},

		collectivesCrumbString() {
			return this.selectedCollective
				? ''
				: t('collectives', 'All collectives')
		},

		collectiveBreadcrumbAriaLabel() {
			return this.selectedCollective.isPageShare
				? t('collectives', 'Breadcrumb for page {name}', { name: this.rootPage.title })
				: t('collectives', 'Breadcrumb for collective {name}', { name: this.selectedCollective.name })
		},

		collectiveBreadcrumbEmoji() {
			return this.selectedCollective.isPageShare
				? this.rootPage.emoji
				: this.selectedCollective.emoji
		},

		collectiveBreadcrumbTitle() {
			return this.selectedCollective.isPageShare
				? this.rootPage.title
				: this.selectedCollective.name
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

	beforeDestroy() {
		window.removeEventListener('keydown', this.handleKeyDown, true)
	},

	methods: {
		...mapActions(usePagesStore, ['getPagesForCollective']),

		scrollToPage() {
			// Scroll current page into view (important when listing parent page)
			this.$nextTick(() => {
				document.getElementById(`picker-page-${this.pageId}`).scrollIntoView({ block: 'center' })
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
				document.getElementById(`picker-page-${this.pageId}`).scrollIntoView({ block: 'center' })
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

.page-picker {
	display: flex;
	flex-direction: column;
}

.crumbs {
	color: var(--color-text-maxcontrast);
	display: inline-flex;
	padding-right: 0;
	padding-bottom: 8px;

	div {
		display: flex;
		text-overflow: ellipsis;
		white-space: nowrap;
		overflow: hidden;
		max-width: 300px;

		.crumb-button {
			color: var(--color-text-maxcontrast);

			&.home {
				padding-left: 0;
				// Remove padding, add margin to not make the button bigger
				padding-right: 0;
				margin-right: var(--button-padding);
				font-weight: bold;
			}
		}

		&.crumbs-home {
			flex-shrink: 0;
		}

		&.crumbs-level {
			display: inline-flex;
			min-width: 65px;

			&:last-child {
				flex-shrink: 0;
			}
		}

		&:last-child {
			.crumb-button {
				color: var(--color-main-text);
			}
		}
	}
}

.picker-list {
	display: inline-block;
	width: 100%;
	height: calc(100% - 34px - 8px - 6px);
	overflow-y: auto;
	flex: 1;

	li a {
		display: flex;
		height: var(--default-clickable-area);
		border-radius: var(--border-radius-element, var(--border-radius-large));
		margin: 4px 0;

		&:not(:last-child) {
			border-bottom: 1px solid var(--color-border);
		}

		&:hover, &:focus, &:active {
			background-color: var(--color-background-hover);
		}

		// Element of the page that is to be copied/moved
		&.self {
			background-color: var(--color-primary-element-light);
		}
	}

	li a.self {
		cursor: default;

		.picker-icon, .picker-title {
			cursor: default;
		}
	}

	.picker-icon {
		display: flex;
		justify-content: center;
		align-items: center;
		width: var(--default-clickable-area);
	}

	.picker-title {
		flex: 1;
		align-content: center;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	.picker-move-buttons {
		display: flex;
		align-items: center;
		padding: 0 12px;
	}
}
</style>
