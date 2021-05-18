<template>
	<div class="app-content-list-item"
		:class="{active: isActive}"
		:style="indentItem"
		@mouseover="hoverItem = true"
		@mouseleave="hoverItem = false">
		<div class="app-content-list-item-icon"
			:style="indentIcon"
			@[isClickable]="toggleCollapsed"
			@mouseover="hoverIcon = true"
			@mouseleave="hoverIcon = false">
			<slot name="icon">
				<div :class="{'page-icon-collapsible': isCollapsible}"
					:style="iconStyle">
					{{ firstGrapheme }}
				</div>
			</slot>
			<TriangleIcon v-if="isCollapsible"
				:title="collapsed ? 'Expand subpage list' : 'Collapse subpage list'"
				:fill-color="hoverIcon ? 'var(--color-primary)' : 'var(--color-main-text)'"
				class="page-icon-badge"
				:class="{'page-icon-badge--rotated': collapsed}" />
		</div>
		<router-link :to="to">
			<div class="app-content-list-item-line-one">
				{{ title }}
			</div>
			<div v-if="$scopedSlots['line-two']"
				class="app-content-list-item-line-two">
				<slot name="line-two" />
			</div>
		</router-link>
		<div class="page-list-item-actions">
			<Actions v-if="isActive || isMobile || hoverItem">
				<slot name="actions" />
			</Actions>
		</div>
	</div>
</template>

<script>

import Actions from '@nextcloud/vue/dist/Components/Actions'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { mapGetters } from 'vuex'
import TriangleIcon from 'vue-material-design-icons/Triangle'

export default {
	name: 'PagesListItem',

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
		collapsed: {
			type: Boolean,
			required: true,
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
	},

	data() {
		return {
			hoverItem: false,
			hoverIcon: false,
		}
	},

	computed: {
		...mapGetters([
			'currentPage',
		]),

		isActive() {
			return this.$store.state.route.path === this.to
		},

		indent() {
			// Limit indention to three to prevent nasty subtrees
			return Math.min(this.level, 3)
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
		toggleCollapsed() {
			this.$emit('toggleCollapsed')
		},
	},
}

</script>

<style lang="scss" scoped>
	.app-content-list-item .app-content-list-item-icon {
		line-height: 40px;
		width: 30px;
		left: 12px;
		font-size: 24px;
	}

	.app-content-list-item .app-content-list-item-icon div {
		border-radius: 3px 12px 3px 3px;
	}

	.app-content-list .app-content-list-item .app-content-list-item-line-one {
		font-size: 120%;
	}

	.app-content-list .app-content-list-item .app-content-list-item-line-two {
		opacity: 1;
	}

	.page-list-item-actions {
		position: absolute;
		top: 0;
		right: 0;
		margin: 0;
	}

	div.app-content-list-item:hover {
		background-color: var(--color-main-background);
	}

	div.app-content-list-item {
		cursor: default;
	}

	.page-icon-collapsible {
		cursor: pointer;
	}

	.material-design-icon.page-icon-badge {
		width: 16px;
		transform: scale(1, 0.8);
	}

	.material-design-icon.page-icon-badge > .material-design-icon__svg {
		width: 16px;
		transform: scale(1, 0.8);
	}

	.page-icon-badge {
		position: absolute;
		bottom: -20px;
		right: -13px;
		padding: 5px 10px;
		background-size: cover;
		cursor: pointer;
		-webkit-transform: rotate(180deg);
		-ms-transform: rotate(180deg);
		transform: rotate(180deg);

		&--rotated {
			bottom: -6px;
			-webkit-transform: rotate(90deg);
			-ms-transform: rotate(90deg);
			transform: rotate(90deg);
		}
	}

</style>
