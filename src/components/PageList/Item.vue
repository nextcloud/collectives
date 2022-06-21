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
					<PagesTemplateIcon v-if="isCollapsible" :size="26" fill-color="var(--color-main-background)" />
					<PageTemplateIcon v-else :size="26" fill-color="var(--color-main-background)" />
				</template>
				<template v-else>
					<PagesIcon v-if="isCollapsible" :size="26" fill-color="var(--color-main-background)" />
					<PageIcon v-else :size="26" fill-color="var(--color-main-background)" />
				</template>
			</slot>
			<TriangleIcon v-if="isCollapsible"
				v-show="!filteredView"
				:title="collapsed(pageId) ? t('collectives', 'Expand subpage list') : t('collectives', 'Collapse subpage list')"
				class="page-icon-badge"
				:class="{'page-icon-badge--rotated': collapsed(pageId)}" />
		</div>
		<router-link :to="to"
			class="app-content-list-item-link">
			<div class="app-content-list-item-line-one"
				:class="{ 'template': isTemplate }">
				{{ title === 'Template' ? t('collectives', 'Template') : title }}
			</div>
		</router-link>
		<div class="page-list-item-actions">
			<Actions>
				<slot name="actions" />
			</Actions>
		</div>
	</div>
</template>

<script>

import Actions from '@nextcloud/vue/dist/Components/Actions'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { generateUrl } from '@nextcloud/router'
import { mapGetters, mapMutations } from 'vuex'
import TriangleIcon from 'vue-material-design-icons/Triangle'
import PageIcon from '../Icon/PageIcon.vue'
import PagesIcon from '../Icon/PagesIcon.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import PagesTemplateIcon from '../Icon/PagesTemplateIcon.vue'
import { scrollToPage } from '../../util/scrollToElement.js'

export default {
	name: 'Item',

	components: {
		Actions,
		PageIcon,
		PagesIcon,
		PageTemplateIcon,
		PagesTemplateIcon,
		TriangleIcon,
	},

	mixins: [
		isMobile,
	],

	props: {
		to: {
			type: String,
			default: '',
		},
		hasChildren: {
			type: Boolean,
			default: false,
		},
		title: {
			type: String,
			required: true,
		},
		level: {
			type: Number,
			required: true,
		},
		filteredView: {
			type: Boolean,
			required: true,
		},
		pageId: {
			type: Number,
			default: 0,
		},
		isTemplate: {
			type: Boolean,
			default: false,
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
			const left = 12 + 12 * this.indent
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
			return (this.level > 0 && this.hasChildren)
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
			width: calc(100% - 28px);
		}

		.page-list-item-actions {
			visibility: visible;
		}
	}

	.template {
		color: var(--color-text-maxcontrast);
	}

	&-line-one {
		font-size: 120%;
	}
}

.app-content-list-item .app-content-list-item-icon {
	display: flex;
	line-height: 40px;
	width: 26px;
	height: 34px;
	left: 12px;
	font-size: 24px;
	background-color: var(--color-background-darker);
	border-radius: 4px;
}

.app-content-list-item-link {
	width: 100%;
	overflow: hidden;
	text-overflow: ellipsis;
}

.page-list-item-actions {
	visibility: hidden;
	position: absolute;
	top: 0;
	right: 0;
	margin: 0;
}

.page-icon-outer {
	display: flex;
}

// Set pointer cursor on page icon if isCollapsible
.page-icon-collapsible {
	cursor: pointer;
}

// Change color of collapse/expand badge when hovering over page icon
.app-content-list-item-icon {
	&:hover, &:focus, &:active {
		.material-design-icon.page-icon-badge > .material-design-icon__svg {
			fill: var(--color-primary);
		}
	}
}
</style>

<style lang="scss">
// Configure collapse/expand badge
.material-design-icon.page-icon-badge {
	position: absolute;
	bottom: -16px;
	right: -14px;
	padding: 5px 10px;
	background-size: cover;
	cursor: pointer;
	-webkit-transform: rotate(180deg);
	-ms-transform: rotate(180deg);
	transform: rotate(180deg);

	&--rotated {
		bottom: -16px;
		-webkit-transform: rotate(90deg);
		-ms-transform: rotate(90deg);
		transform: rotate(90deg);
	}
}

.material-design-icon.page-icon-badge > .material-design-icon__svg {
	width: 16px;
	transform: scale(1, 0.7);
	fill: var(--color-main-text);
}
</style>
