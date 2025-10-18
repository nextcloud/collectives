<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		:id="pageElementId"
		:data-page-id="pageId"
		class="app-content-list-item"
		:class="{
			active: isActive,
			mobile: isMobile,
			toplevel: level === 0,
			highlight: isHighlighted,
			'dragged-over-target': isDraggedOverTarget,
			'highlight-target': isHighlightedTarget,
			'highlight-animation': isHighlightAnimation,
		}"
		draggable="true"
		@dragstart="onDragstart"
		@dragend="onDragend"
		@dragover.prevent="onDragover"
		@dragleave="onDragleave"
		@drop="onDrop">
		<div
			class="app-content-list-item-icon"
			:tabindex="isCollapsible ? '0' : null"
			@keyup.enter="toggleCollapsedOrRoute"
			@click="toggleCollapsedOrRoute">
			<slot name="icon">
				<template v-if="emoji">
					<div class="item-icon-emoji" :class="{ 'root-page': isRootPage }">
						{{ emoji }}
					</div>
				</template>
				<template v-else-if="isLandingPage">
					<CollectivesIcon :size="22" fill-color="var(--color-main-text)" />
				</template>
				<template v-else>
					<PageIcon :size="22" fill-color="var(--color-background-darker)" />
				</template>
			</slot>
			<template v-if="isCollapsible">
				<MenuRightIcon
					v-show="!filteredView"
					:size="18"
					fill-color="var(--color-main-text)"
					:title="t('collectives', 'Expand subpage list')"
					class="item-icon-badge"
					:class="isCollapsed(pageId) ? 'collapsed' : 'expanded'" />
			</template>
			<template v-if="showFavoriteStar">
				<StarIconFilled
					v-show="!filteredView"
					:size="18"
					fill-color="var(--color-favorite)"
					:title="t('collectives', 'Favorite')"
					class="item-icon-favorite" />
			</template>
		</div>
		<router-link
			:to="to"
			draggable="false"
			class="app-content-list-item-link">
			<div
				ref="page-title"
				:title="pageTitleIfTruncated"
				class="app-content-list-item-line-one"
				@click="expandAndScroll">
				{{ pageTitleString }}
			</div>
		</router-link>
		<div class="page-list-item-actions">
			<PageActionMenu
				v-if="canEdit || isLandingPage"
				:page-id="pageId"
				:page-url="to"
				:parent-id="parentId"
				:timestamp="timestamp"
				:last-user-id="lastUserId"
				:last-user-display-name="lastUserDisplayName"
				:is-landing-page="isLandingPage"
				:in-page-list="true"
				:network-online="networkOnline" />
			<NcActions v-if="canEdit">
				<NcActionButton
					class="action-button-add"
					:disabled="!networkOnline || loading(`template-list-${templatesCollectiveId}`)"
					@click="onNewPage">
					<template #icon>
						<PlusIcon :size="20" fill-color="var(--color-main-text)" />
					</template>
					{{ addPageString }}
				</NcActionButton>
			</NcActions>
		</div>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { NcActionButton, NcActions } from '@nextcloud/vue'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import MenuRightIcon from 'vue-material-design-icons/MenuRightOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import StarIconFilled from 'vue-material-design-icons/Star.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import PageIcon from '../Icon/PageIcon.vue'
import PageActionMenu from '../Page/PageActionMenu.vue'
import pageMixin from '../../mixins/pageMixin.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { useTemplatesStore } from '../../stores/templates.js'
import { scrollToPage } from '../../util/scrollToElement.js'

export default {
	name: 'PageListItem',

	components: {
		CollectivesIcon,
		MenuRightIcon,
		NcActionButton,
		NcActions,
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

		isRootPage: {
			type: Boolean,
			default: false,
		},

		isLandingPage: {
			type: Boolean,
			default: false,
		},

		filteredView: {
			type: Boolean,
			required: true,
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
			pageTitleIsTruncated: false,
			isHighlightedTarget: false,
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

		...mapState(useTemplatesStore, ['hasTemplates']),

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
			// root page and favorites are not collapsible
			return this.level > 0 && !this.inFavoriteList && this.hasVisibleSubpages
		},

		showFavoriteStar() {
			return !this.inFavoriteList && this.isFavoritePage(this.currentCollective.id, this.pageId)
		},

		pageTitleString() {
			return this.title
		},

		pageTitleIfTruncated() {
			return this.pageTitleIsTruncated ? this.pageTitleString : null
		},

		addPageString() {
			return this.isLandingPage
				? t('collectives', 'Add a page')
				: t('collectives', 'Add a subpage')
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
				&& !this.pageParents(this.pageId).includes(this.draggedPageId)
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

		this.pageTitleIsTruncated = this.$refs['page-title'].scrollWidth > this.$refs['page-title'].clientWidth
	},

	methods: {
		...mapActions(usePagesStore, [
			'expand',
			'setNewPageParentId',
			'setDragoverTargetPage',
			'setDraggedPageId',
			'toggleCollapsed',
		]),

		toggleCollapsedOrRoute(event) {
			if (this.isCollapsible) {
				event.stopPropagation()
				this.toggleCollapsed(this.pageId)
			} else {
				this.expandAndScroll()
				if (this.currentPage.id !== this.pageId) {
					this.$router.push(this.to)
				}
			}
		},

		expandAndScroll() {
			this.expand(this.pageId)
			// Scroll favored page in page list into viewport
			if (this.inFavoriteList) {
				scrollToPage(this.pageId)
			}
		},

		onNewPage() {
			this.hasTemplates
				? this.setNewPageParentId(this.pageId)
				: this.newPage(this.pageId)
		},

		onDragstart(event) {
			// Don't set root page or favorite as dragged page
			if (this.isRootPage || this.inFavoriteList) {
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
			this.isHighlightedTarget = false
			this.setDragoverTargetPage(false)
			this.setDraggedPageId(null)
		},

		onDragover() {
			if (this.isPotentialDropTarget) {
				this.isHighlightedTarget = true
				this.setDragoverTargetPage(true)
			}
		},

		onDragleave() {
			this.isHighlightedTarget = false
			this.setDragoverTargetPage(false)
		},

		onDrop() {
			if (this.isDropTarget
				// Ingore if self is direct parent of dragged element
				&& this.pageParent(this.draggedPageId) !== this.pageId) {
				this.move(this.pageParent(this.draggedPageId), this.pageId, this.draggedPageId, 0)
			}
			this.isHighlightedTarget = false
			this.setDragoverTargetPage(false)
			this.setDraggedPageId(null)
		},
	},
}

</script>

<style lang="scss" scoped>
@use '../../css/animation';

.app-content-list-item {
	box-sizing: border-box;
	height: var(--default-clickable-area);
	// border-bottom: 4px solid var(--color-main-background);
	margin-bottom: 4px;

	padding: 0;
	border-radius: var(--border-radius-large);

	&.toplevel {
		font-size: 1.2em;
	}

	&.active {
		background-color: var(--color-primary-element-light);

		span.item-icon-badge {
			background-color: var(--color-primary-element-light);
		}

		span.item-icon-favorite {
			background-color: var(--color-primary-element-light);
		}
	}

	&:hover, &:focus, &:active, &.highlight {
		background-color: var(--color-background-hover);

		span.item-icon-badge {
			background-color: var(--color-background-hover);
		}

		span.item-icon-favorite {
			background-color: var(--color-background-hover);
		}
	}

	&.highlight-animation {
		animation: highlight-animation 5s 1;

		span.item-icon-badge {
			animation: highlight-animation 5s 1;
		}
	}

	&.highlight-target {
		// background-color: var(--color-primary-element-light);
		border: 1px solid var(--color-border-maxcontrast);
	}

	&.dragged-over-target {
		// Make cloned drag element less visible if dragged over a target page
		opacity: .3;
	}

	&.active, &.toplevel, &.mobile, &:hover, &:focus, &:active {
		// Shorter width to prevent collision with actions
		.app-content-list-item-link {
			width: calc(100% - 88px);
		}

		.page-list-item-actions {
			visibility: visible;
		}
	}

	.app-content-list-item-icon {
		display: flex;
		justify-content: center;
		align-items: center;
		font-size: 20px;

		.item-icon-emoji {
			cursor: pointer;

			&.root-page {
				margin: -3px 0;
			}
		}

		.material-design-icon {
			cursor: pointer;
		}

		// Configure collapse/expand badge
		.item-icon-badge {
			position: absolute;
			bottom: -2px;
			right: -1px;
			cursor: pointer;
			border: 0;
			border-radius: 50%;
			background-color: var(--color-main-background);
			transition: transform var(--animation-slow);

			&.expanded {
				transform: rotate(90deg);
			}
		}

		// Configure favorite icon
		.item-icon-favorite {
			position: absolute;
			top: 0;
			right: -1px;
			cursor: pointer;
			border: 0;
			border-radius: 50%;
			background-color: var(--color-main-background);
		}
	}

	.app-content-list-item-line-one {
		padding-left: 40px;
	}

	.app-content-list-item-link {
		display: flex;
		align-items: center;
		height: 100%;
		width: 100%;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.page-list-item-actions {
	visibility: hidden;
	display: flex;
	gap: 2px;
	position: absolute;
	top: 0;
	right: 0;
	margin: 0;
}
</style>
