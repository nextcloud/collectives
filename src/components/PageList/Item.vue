<template>
	<div class="app-content-list-item"
		:class="{active: isActive}"
		:style="indentItem"
		draggable
		@dragstart="setDragData">
		<div class="app-content-list-item-icon"
			:style="indentIcon"
			:tabindex="isClickable ? '0' : null"
			@keypress.enter="toggleCollapsed(pageId)"
			@[isClickable]="toggleCollapsed(pageId)">
			<slot name="icon">
				<div v-if="isTemplate" :class="isCollapsible ? 'icon-pages-template-white' : 'icon-page-template-white'" />
				<div v-else :class="isCollapsible ? 'icon-pages-white' : 'icon-page-white'" />
			</slot>
			<TriangleIcon v-if="isCollapsible"
				:title="collapsed(pageId) ? t('collectives', 'Expand subpage list') : t('collectives', 'Collapse subpage list')"
				class="page-icon-badge"
				:class="{'page-icon-badge--rotated': collapsed(pageId)}" />
		</div>
		<router-link :to="to">
			<div class="app-content-list-item-line-one"
				:class="{ 'app-content-list-item-line-one--level0': level === 0, 'app-content-list-item-template': isTemplate }">
				{{ title === 'Template' ? t('collectives', 'Template') : title }}
			</div>
			<div v-if="$scopedSlots['line-two']"
				class="app-content-list-item-line-two">
				<slot name="line-two" />
			</div>
		</router-link>
		<div class="page-list-item-actions"
			:class="{'page-list-item-actions--display': level === 0 || isActive || isMobile}">
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

export default {
	name: 'Item',

	components: {
		Actions,
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
		collapsible: {
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
			return this.$store.state.route.path === this.to
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

		iconStyle() {
			const id = `Page-${this.pageId}`
			const c = id.toRgb()
			return `background-color: rgb(${c.r}, ${c.g}, ${c.b})`
		},

		// UTF8 friendly way of getting first 'letter'
		firstGrapheme() {
			return this.title[Symbol.iterator]().next().value
		},

		isCollapsible() {
			// Collective landing page is not collapsible
			return (this.level > 0 && this.collapsible)
		},

		isClickable() {
			return this.isCollapsible ? 'click' : null
		},
	},

	methods: {
		...mapMutations(['toggleCollapsed']),

		setDragData(ev) {
			const path = generateUrl(`/apps/collectives${this.to}`)
			const href = new URL(path, window.location).href
			const html = `<a href=${href}>${this.title}</a>`
			ev.dataTransfer.effectAllowed = 'move'
			ev.dataTransfer.setData('text/plain', href)
			ev.dataTransfer.setData('text/uri-list', href)
			ev.dataTransfer.setData('text/html', html)
		},
	},
}

</script>

<style lang="scss" scoped>
.app-content-list-item .app-content-list-item-icon {
	line-height: 40px;
	width: 26px;
	height: 34px;
	left: 12px;
	font-size: 24px;
	background-color: var(--color-background-darker);
	border-radius: 4px;
}

.app-content-list-item .app-content-list-item-icon div {
	border-radius: 3px 12px 3px 3px;
}

.app-content-list .app-content-list-item .app-content-list-item-line-one {
	font-size: 120%;
	&--level0 {
		font-weight: bold;
	}
}

.app-content-list-item-template {
	color: var(--color-text-lighter);
}

.app-content-list .app-content-list-item .app-content-list-item-line-two {
	opacity: 1;
}

.page-list-item-actions {
	visibility: hidden;
	position: absolute;
	top: 0;
	right: 0;
	margin: 0;
	&--display {
		// Always display page actions if active or on mobile
		visibility: visible;
	}
}

.app-content-list-item.active {
	background-color: var(--color-primary-light);
}

// Display page actions on hovering the page list item
.app-content-list-item:hover {
	background-color: var(--color-background-hover);
	.page-list-item-actions {
		visibility: visible;
	}
}

div.app-content-list-item {
	cursor: default;
}

// Set pointer cursor on page icon if isCollapsible
.page-icon-collapsible {
	cursor: pointer;
}

// Configure collapse/expand badge
.page-icon-badge {
	position: absolute;
	bottom: -20px;
	right: -14px;
	padding: 5px 10px;
	background-size: cover;
	cursor: pointer;
	-webkit-transform: rotate(180deg);
	-ms-transform: rotate(180deg);
	transform: rotate(180deg);

	&--rotated {
		bottom: -21px;
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

// Change color of collapse/expand badge when hovering over page icon
.app-content-list-item-icon:hover {
	.material-design-icon.page-icon-badge > .material-design-icon__svg {
		fill: var(--color-primary);
	}
}
</style>
