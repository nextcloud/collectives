<template>
	<div :class="{active: isActive}"
		class="app-content-list-item"
		:style="indentItem"
		@mouseover="hover = true"
		@mouseleave="hover = false">
		<div class="app-content-list-item-icon"
			:style="indentIcon">
			<button v-if="isCollapsible"
				class="icon-collapse icon-triangle-s"
				:class="{'icon-collapse--rotated':collapsed}"
				@click="toggleCollapsed" />
			<slot v-else name="icon">
				<div :style="iconStyle">
					{{ firstGrapheme }}
				</div>
			</slot>
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
			<Actions v-if="isActive || isMobile || hover">
				<slot name="actions" />
			</Actions>
		</div>
	</div>
</template>

<script>

import Actions from '@nextcloud/vue/dist/Components/Actions'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { mapGetters } from 'vuex'

export default {
	name: 'PagesListItem',

	components: {
		Actions,
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
			hover: false,
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
			return (this.level === 0) ? false : this.collapsible
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

	// Copied over from @nextcloud-vue component AppNavigationIconCollapsible
	.icon-collapse {
		transition: opacity var(--animation-quick) ease-in-out;
		color: var(--color-main-text);
		border: none;
		width: 100%;
		height: 100%;
		background-size: contain;

		&:hover{
			color: var(--color-primary);
		}
		&--rotated {
			-webkit-transform: rotate(-90deg);
			-ms-transform: rotate(-90deg);
			transform: rotate(-90deg);
			color: var(--color-main-text);
			&:hover{
				color: var(--color-primary);
			}
		}
	}

</style>
