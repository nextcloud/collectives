<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigationItem
		:id="pageElementId"
		:data-page-id="pageId"
		:name="pageTitleString"
		:to="to"
		:open="isCollapsible ? !isCollapsed(pageId) : undefined"
		:allowCollapse="isCollapsible"
		class="page-list-item"
		:class="{
			mobile: isMobile,
			highlight: isHighlighted,
			'dragged-over-target': isDraggedOverTarget,
			'highlight-target': isHighlightedTarget,
			'highlight-animation': isHighlightAnimation,
		}"
		draggable="true"
		@update:open="toggleCollapsed(pageId)"
		@dragstart="onDragstart"
		@dragend="onDragend"
		@dragover.prevent="onDragover"
		@dragleave="onDragleave"
		@drop="onDrop"
		@click="expandAndScroll">
		<template #icon>
			<slot name="icon">
				<template v-if="emoji">
					<div class="item-icon-emoji">
						{{ emoji }}
					</div>
				</template>
				<template v-else>
					<PageIcon :size="22" fillColor="var(--color-background-maxcontrast)" />
				</template>
			</slot>
			<template v-if="showFavoriteStar">
				<StarIconFilled
					v-show="!filteredView"
					:size="18"
					fillColor="var(--color-favorite)"
					:title="t('collectives', 'Favorite')"
					class="item-icon-favorite" />
			</template>
		</template>
		<template v-if="canEdit" #extra>
			<div class="page-list-item-actions">
				<PageActionMenu
					:pageId
					:pageUrl="to"
					:parentId
					:timestamp
					:lastUserId
					:lastUserDisplayName
					inPageList
					:networkOnline />
				<NcActions container="#app-navigation-vue">
					<NcActionButton
						class="action-button-add"
						:disabled="!networkOnline || loading(`template-list-${templatesCollectiveId}`)"
						@click="onNewPage">
						<template #icon>
							<PlusIcon :size="20" fillColor="var(--color-main-text)" />
						</template>
						{{ t('collectives', 'Add a subpage') }}
					</NcActionButton>
				</NcActions>
			</div>
		</template>
		<slot />
	</NcAppNavigationItem>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import StarIconFilled from 'vue-material-design-icons/Star.vue'
import PageIcon from '../Icon/PageIcon.vue'
import PageActionMenu from '../Page/PageActionMenu.vue'
import pageMixin from '../../mixins/pageMixin.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { scrollToPage } from '../../util/scrollToElement.js'

export default {
	name: 'PageListItem',

	components: {
		NcActionButton,
		NcActions,
		NcAppNavigationItem,
		PageIcon,
		PageActionMenu,
		PlusIcon,
		StarIconFilled,
	},

	mixins: [
		pageMixin,
	],

	props: {
		to: {
			type: String,
			default: '',
		},

		pageId: {
			type: Number,
			required: true,
		},

		parentId: {
			type: Number,
			required: true,
		},

		title: {
			type: String,
			required: true,
		},

		timestamp: {
			type: Number,
			required: true,
		},

		lastUserId: {
			type: String,
			default: null,
		},

		lastUserDisplayName: {
			type: String,
			default: null,
		},

		emoji: {
			type: String,
			default: '',
		},

		level: {
			type: Number,
			required: true,
		},

		canEdit: {
			type: Boolean,
			default: false,
		},

		inFavoriteList: {
			type: Boolean,
			default: false,
		},

		hasVisibleSubpages: {
			type: Boolean,
			default: false,
		},

		filteredView: {
			type: Boolean,
			default: false,
		},

		networkOnline: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const isMobile = useIsMobile()
		return { isMobile }
	},

	data() {
		return {
			isHighlightedTarget: false,
			dragoverTimer: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'isFavoritePage',
			'templatesCollectiveId',
		]),

		...mapState(usePagesStore, [
			'isCollapsed',
			'currentPage',
			'disableDragndropSortOrMove',
			'draggedPageId',
			'highlightAnimationPageId',
			'highlightPageId',
			'isDragoverTargetPage',
			'pageParent',
			'pageParents',
		]),

		pageElementId() {
			return this.inFavoriteList
				? `page-favorite-${this.pageId}`
				: `page-${this.pageId}`
		},

		isActive() {
			return this.currentPage
				&& this.currentPage.id === this.pageId
		},

		isCollapsible() {
			// favorites are not collapsible
			return this.level > 0 && !this.inFavoriteList && this.hasVisibleSubpages
		},

		showFavoriteStar() {
			return !this.inFavoriteList && this.isFavoritePage(this.currentCollective.id, this.pageId)
		},

		pageTitleString() {
			return this.title
		},

		isHighlighted() {
			return this.highlightPageId === this.pageId
		},

		isDragged() {
			return this.draggedPageId === this.pageId
		},

		isDraggedOverTarget() {
			return this.isDragged && this.isDragoverTargetPage
		},

		isPotentialDropTarget() {
			// IMPORTANT: needs to be synchronized with custom drag/drop events in DraggableElement.vue
			return !this.disableDragndropSortOrMove
				// Ignore if draggedPageId is unset
				&& this.draggedPageId
				// Ignore if self is the dragged element
				&& !this.isDragged
				// Ignore if in filtered view
				&& !this.filteredView
				// Ignore if inside favorite list
				&& !this.inFavoriteList
				// Ignore if dragged element is a parent of self
				&& !this.pageParents(this.pageId).some((page) => page.id === this.draggedPageId)
		},

		isDropTarget() {
			return this.isPotentialDropTarget
				&& this.isDragoverTargetPage
		},

		isHighlightAnimation() {
			return this.highlightAnimationPageId === this.pageId
		},
	},

	mounted() {
		// Scroll to item at initial mount if it's currentPage
		if (this.isActive && !this.inFavoriteList) {
			scrollToPage(this.pageId)
		}
	},

	methods: {
		t,

		...mapActions(usePagesStore, [
			'expand',
			'setDragoverTargetPage',
			'setDraggedPageId',
			'toggleCollapsed',
		]),

		expandAndScroll() {
			this.expand(this.pageId)
			// Scroll favored page in page list into viewport
			if (this.inFavoriteList) {
				scrollToPage(this.pageId)
			}
			// Close the nav sidebar on mobile after navigating to a page
			if (this.isMobile) {
				emit('toggle-navigation', { open: false })
			}
		},

		onNewPage() {
			this.addPage(this.pageId)
		},

		onDragstart(event) {
			// Don't set favorite as dragged page
			if (this.inFavoriteList) {
				return
			}

			// Set dragged page (allows to move the page)
			this.setDraggedPageId(this.pageId)

			// Set drag data
			const path = generateUrl(`/apps/collectives${this.to}`)
			const href = new URL(path, window.location).href
			const html = `<a href=${href}>${this.title}</a>`
			event.dataTransfer.effectAllowed = 'copyMove'
			event.dataTransfer.setData('text/plain', href)
			event.dataTransfer.setData('text/uri-list', href)
			event.dataTransfer.setData('text/html', html)
		},

		onDragend() {
			clearTimeout(this.dragoverTimer)
			this.isHighlightedTarget = false
			this.setDragoverTargetPage(false)
			this.setDraggedPageId(null)
		},

		onDragover() {
			if (this.isPotentialDropTarget) {
				clearTimeout(this.dragoverTimer)
				this.dragoverTimer = setTimeout(() => {
					this.isHighlightedTarget = true
					this.setDragoverTargetPage(true)
				}, 20)
			}
		},

		onDragleave() {
			clearTimeout(this.dragoverTimer)
			this.isHighlightedTarget = false
			this.setDragoverTargetPage(false)
		},

		onDrop(event) {
			if (this.isDropTarget
				// Ignore if self is direct parent of dragged element
				&& this.pageParent(this.draggedPageId) !== this.pageId) {
				// Claim this drop: prevent it from also bubbling to sortable.js's
				// own drop handling, which would otherwise process the same drop
				// a second time as a sibling reorder instead of a move into this page
				event.stopPropagation()
				this.move(this.pageParent(this.draggedPageId), this.pageId, this.draggedPageId, 0)
			}
			clearTimeout(this.dragoverTimer)
			this.isHighlightedTarget = false
			this.setDragoverTargetPage(false)
			this.setDraggedPageId(null)
		},
	},
}

</script>

<style lang="scss" scoped>
@use '../../css/animation';

.page-list-item {
	&.highlight :deep(.app-navigation-entry) {
		background-color: var(--color-background-hover);
	}

	&.highlight-animation :deep(.app-navigation-entry) {
		animation: highlight-animation 5s 1;
	}

	&.highlight-target :deep(.app-navigation-entry) {
		border: 1px solid var(--color-border-maxcontrast);
	}

	&.dragged-over-target {
		// Make cloned drag element less visible if dragged over a target page
		opacity: .3;
	}
}

.item-icon-emoji {
	cursor: pointer;
}

// Anchor the favorite badge to the icon box itself, not the whole row
:deep(.app-navigation-entry-icon) {
	position: relative;
}

.item-icon-favorite {
	position: absolute;
	top: 0;
	right: -1px;
	cursor: pointer;
	border: 0;
	border-radius: 50%;
}

// Push the built-in collapse arrow after the actions/add-page buttons
:deep(.icon-collapse) {
	order: 1;
}

.page-list-item-actions {
	order: 0;
	visibility: hidden;
	display: flex;
	gap: 2px;
}

.page-list-item.mobile .page-list-item-actions {
	visibility: visible;
}

:deep(.app-navigation-entry:hover) .page-list-item-actions,
:deep(.app-navigation-entry:focus-within) .page-list-item-actions,
:deep(.app-navigation-entry.active) .page-list-item-actions {
	visibility: visible;
}
</style>
