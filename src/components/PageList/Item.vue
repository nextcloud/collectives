<template>
	<div :id="`page-${pageId}`"
		:data-page-id="pageId"
		class="app-content-list-item"
		:class="{
			active: isActive,
			mobile: isMobile,
			toplevel: level === 0,
			highlight: isHighlighted,
		}"
		draggable
		@dragstart="setDragData">
		<div class="app-content-list-item-icon"
			:tabindex="isCollapsible ? '0' : null"
			@keypress.enter="toggleCollapsedOrRoute()"
			@click="toggleCollapsedOrRoute()">
			<slot name="icon">
				<template v-if="isTemplate">
					<PageTemplateIcon :size="22" fill-color="var(--color-background-darker)" />
				</template>
				<template v-else-if="emoji">
					<div class="item-icon-emoji" :class="{'landing-page': isLandingPage}">
						{{ emoji }}
					</div>
				</template>
				<template v-else>
					<PageIcon :size="22" fill-color="var(--color-background-darker)" />
				</template>
			</slot>
			<template v-if="isCollapsible">
				<MenuRightIcon v-show="!filteredView"
					:size="18"
					fill-color="var(--color-main-text)"
					:title="t('collectives', 'Expand subpage list')"
					class="item-icon-badge"
					:class="collapsed(pageId) ? 'collapsed' : 'expanded'" />
			</template>
		</div>
		<router-link :to="to"
			draggable="false"
			class="app-content-list-item-link">
			<div ref="page-title"
				v-tooltip="pageTitleIfTruncated"
				class="app-content-list-item-line-one"
				:class="{ 'template': isTemplate }"
				@click="expand(pageId)">
				{{ pageTitleString }}
			</div>
		</router-link>
		<div v-if="canEdit" class="page-list-item-actions">
			<PageActionMenu :page-id="pageId"
				:page-url="to"
				:parent-id="parentId"
				:timestamp="timestamp"
				:last-user-id="lastUserId"
				:last-user-display-name="lastUserDisplayName"
				:is-landing-page="isLandingPage"
				:is-template="isTemplate"
				:in-page-list="true" />
			<NcActions>
				<NcActionButton class="action-button-add" @click="newPage(pageId)">
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
import { mapGetters, mapMutations, mapState } from 'vuex'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { NcActionButton, NcActions } from '@nextcloud/vue'
import MenuRightIcon from 'vue-material-design-icons/MenuRight.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import pageMixin from '../../mixins/pageMixin.js'
import PageIcon from '../Icon/PageIcon.vue'
import PageActionMenu from '../Page/PageActionMenu.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import { scrollToPage } from '../../util/scrollToElement.js'

export default {
	name: 'Item',

	components: {
		NcActionButton,
		NcActions,
		MenuRightIcon,
		PageIcon,
		PageActionMenu,
		PageTemplateIcon,
		PlusIcon,
	},

	mixins: [
		isMobile,
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
		isTemplate: {
			type: Boolean,
			default: false,
		},
		hasVisibleSubpages: {
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
	},

	data() {
		return {
			pageTitleIsTruncated: false,
		}
	},

	computed: {
		...mapState({
			highlightPageId: (state) => state.pages.highlightPageId,
		}),

		...mapGetters([
			'currentPage',
			'collapsed',
		]),

		isActive() {
			return this.currentPage
				&& this.currentPage.id === this.pageId
		},

		indent() {
			// Start indention at level 2. And limit to 5 to prevent nasty subtrees
			return Math.min(Math.max(0, this.level - 1), 4)
		},

		indentItem() {
			const left = 28 * this.indent
			return `padding-left: ${left}px`
		},

		// UTF8 friendly way of getting first 'letter'
		firstGrapheme() {
			return this.title[Symbol.iterator]().next().value
		},

		isCollapsible() {
			// Collective landing page is not collapsible
			return (this.level > 0 && this.hasVisibleSubpages)
		},

		pageTitleString() {
			return this.title === 'Template' ? t('collectives', 'Template') : this.title
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
	},

	mounted() {
		// Scroll to item at initial mount if it's currentPage
		if (this.isActive) {
			scrollToPage(this.pageId)
		}

		this.pageTitleIsTruncated = this.$refs['page-title'].scrollWidth > this.$refs['page-title'].clientWidth
	},

	methods: {
		...mapMutations([
			'expand',
			'toggleCollapsed',
		]),

		setDragData(ev) {
			const path = generateUrl(`/apps/collectives${this.to}`)
			const href = new URL(path, window.location).href
			const html = `<a href=${href}>${this.title}</a>`
			ev.dataTransfer.effectAllowed = 'copyMove'
			ev.dataTransfer.setData('text/plain', href)
			ev.dataTransfer.setData('text/uri-list', href)
			ev.dataTransfer.setData('text/html', html)
		},

		toggleCollapsedOrRoute(ev) {
			if (this.isCollapsible) {
				event.stopPropagation()
				this.toggleCollapsed(this.pageId)
			} else {
				if (this.currentPage.id !== this.pageId) {
					this.$router.push(this.to)
				}
			}
		},
	},
}

</script>

<style lang="scss" scoped>
.app-content-list-item {
	height: unset;
	border-bottom: 4px solid var(--color-main-background);

	padding-left: 0;
	border-radius: var(--border-radius-large);

	&.toplevel {
		font-size: 1.2em;
	}

	&.active {
		background-color: var(--color-primary-light);

		span.item-icon-badge {
			background-color: var(--color-primary-light);
		}
	}

	&:hover, &:focus, &:active, &.highlight {
		background-color: var(--color-background-hover);

		span.item-icon-badge {
			background-color: var(--color-background-hover);
		}
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

	.template {
		color: var(--color-text-maxcontrast);
	}

	.app-content-list-item-icon {
		display: flex;
		justify-content: center;
		align-items: center;
		// Emojis are too big with default 1.5em
		font-size: 1.3em;

		.item-icon-emoji {
			cursor: pointer;

			&.landing-page {
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
	}

	.app-content-list-item-line-one {
		padding-left: 40px;
	}

	.app-content-list-item-link {
		width: 100%;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.page-list-item-actions {
	visibility: hidden;
	display: flex;
	position: absolute;
	top: 0;
	right: 0;
	margin: 0;
}
</style>
