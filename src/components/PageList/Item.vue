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
					<PageTemplateIcon :size="24" fill-color="var(--color-background-darker)" decorative />
				</template>
				<template v-else-if="emoji">
					<div class="icon-emoji" :class="{'landing-page': isLandingPage}">
						{{ emoji }}
					</div>
				</template>
				<template v-else>
					<PageIcon :size="24" fill-color="var(--color-background-darker)" decorative />
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
			class="app-content-list-item-link">
			<div ref="page-title"
				v-tooltip="pageTitleIfTruncated"
				class="app-content-list-item-line-one"
				:class="{ 'template': isTemplate }">
				{{ pageTitle }}
			</div>
		</router-link>
		<div v-if="canEdit" class="page-list-item-actions">
			<PageActionMenu :page-id="pageId"
				:page-url="to"
				:parent-page-id="parentPageId"
				:timestamp="timestamp"
				:last-user-id="lastUserId"
				:is-landing-page="isLandingPage"
				:is-template="isTemplate" />
			<Actions>
				<ActionButton class="action-button-add" @click="newPage(pageId)">
					<template #icon>
						<PlusIcon :size="20" fill-color="var(--color-main-text)" decorative />
					</template>
					{{ addPageString }}
				</ActionButton>
			</Actions>
		</div>
	</div>
</template>

<script>

import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import pageMixin from '../../mixins/pageMixin.js'
import { generateUrl } from '@nextcloud/router'
import { mapGetters, mapMutations } from 'vuex'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import MenuRightIcon from 'vue-material-design-icons/MenuRight'
import PageIcon from '../Icon/PageIcon.vue'
import PageActionMenu from '../Page/PageActionMenu.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import PlusIcon from 'vue-material-design-icons/Plus'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import { scrollToPage } from '../../util/scrollToElement.js'

export default {
	name: 'Item',

	components: {
		ActionButton,
		Actions,
		MenuRightIcon,
		PageIcon,
		PageActionMenu,
		PageTemplateIcon,
		PlusIcon,
	},

	directives: {
		Tooltip,
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

		pageTitle() {
			return this.title === 'Template' ? t('collectives', 'Template') : this.title
		},

		pageTitleIfTruncated() {
			return this.pageTitleIsTruncated ? this.pageTitle : null
		},

		addPageString() {
			return this.isLandingPage
				? t('collectives', 'Add a page')
				: t('collectives', 'Add a subpage')
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

		span.item-icon-badge {
			background-color: var(--color-primary-light);
		}
	}

	&:hover, &:focus, &:active {
		background-color: var(--color-background-hover);

		span.item-icon-badge {
			background-color: var(--color-background-hover);
		}
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
