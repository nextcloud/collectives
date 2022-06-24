<template>
	<div :id="`page-${pageId}`"
		class="app-content-list-item"
		:class="{active: isActive, mobile: isMobile, toplevel: level === 0}"
		:style="indentItem"
		draggable
		@dragstart="setDragData">
		<div class="app-content-list-item-icon"
			:style="indentIcon"
			:tabindex="isCollapsible ? '0' : null"
			@keypress.enter="toggleCollapsedOrRoute()"
			@click="toggleCollapsedOrRoute()">
			<slot name="icon">
				<template v-if="isTemplate">
					<PageTemplateIcon :size="24" fill-color="var(--color-background-darker)" />
				</template>
				<template v-else-if="emoji">
					<div class="icon-emoji" :class="{'landing-page': isLandingPage}">
						{{ emoji }}
					</div>
				</template>
				<template v-else>
					<PageIcon :size="24" fill-color="var(--color-background-darker)" />
				</template>
			</slot>
			<template v-if="isCollapsible">
				<ChevronRightIcon v-show="!filteredView"
					:size="22"
					fill-color="var(--color-main-text)"
					:title="t('collectives', 'Expand subpage list')"
					class="item-icon-badge"
					:class="collapsed(pageId) ? 'collapsed' : 'expanded'" />
			</template>
		</div>
		<router-link :to="to"
			class="app-content-list-item-link">
			<div class="app-content-list-item-line-one"
				:class="{ 'template': isTemplate }">
				{{ title === 'Template' ? t('collectives', 'Template') : title }}
			</div>
		</router-link>
		<PageListActions :page-id="pageId"
			:parent-page-id="parentPageId"
			:timestamp="timestamp"
			:last-user-id="lastUserId"
			:is-landing-page="isLandingPage"
			:is-template="isTemplate"
			:has-template="hasTemplate"
			:has-subpages="hasSubpages" />
	</div>
</template>

<script>

import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { generateUrl } from '@nextcloud/router'
import { mapGetters, mapMutations } from 'vuex'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight'
import PageIcon from '../Icon/PageIcon.vue'
import PageListActions from './PageListActions.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import { scrollToPage } from '../../util/scrollToElement.js'

export default {
	name: 'Item',

	components: {
		ChevronRightIcon,
		PageIcon,
		PageListActions,
		PageTemplateIcon,
	},

	mixins: [
		isMobile,
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
		parentPageId: {
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
			required: true,
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
		hasTemplate: {
			type: Boolean,
			default: false,
		},
		hasSubpages: {
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

	computed: {
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

		indentIcon() {
			const left = 12 * this.indent
			return `left: ${left}px`
		},

		indentItem() {
			const left = 7 + 12 * this.indent
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
	},

	mounted() {
		// Scroll to item at initial mount if it's currentPage
		if (this.isActive) {
			scrollToPage(this.pageId)
		}
	},

	methods: {
		...mapMutations([
			'toggleCollapsed',
		]),

		setDragData(ev) {
			const path = generateUrl(`/apps/collectives${this.to}`)
			const href = new URL(path, window.location).href
			const html = `<a href=${href}>${this.title}</a>`
			ev.dataTransfer.effectAllowed = 'move'
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
	margin-bottom: 4px;
	border-radius: var(--border-radius-large);

	&.toplevel {
		font-weight: bold;
	}

	&.active {
		background-color: var(--color-primary-light);
	}

	&:hover, &:focus, &:active {
		background-color: var(--color-background-hover);
	}

	&.active, &.toplevel, &.mobile, &:hover, &:focus, &:active {
		// Shorter width to prevent collision with actions
		.app-content-list-item-link {
			width: calc(100% - 64px);
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

		.icon-emoji {
			cursor: pointer;
			font-size: 18px;

			&.landing-page {
				margin: -3px 0;
				font-size: 24px;
			}
		}

		.material-design-icon {
			cursor: pointer;
		}

		// Configure collapse/expand badge
		.item-icon-badge {
			position: absolute;
			bottom: -2px;
			right: 2px;
			cursor: pointer;
			transition: transform var(--animation-slow);

			&.expanded {
				transform: rotate(90deg);
			}
		}
	}

	.app-content-list-item-line-one {
		padding-left: 36px;
		font-size: 120%;
	}

	.app-content-list-item-link {
		width: 100%;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.page-list-item-actions {
	visibility: hidden;
	position: absolute;
	top: 0;
	right: 0;
	margin: 0;
}
</style>
