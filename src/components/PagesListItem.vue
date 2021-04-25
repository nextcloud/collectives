<template>
	<router-link :to="to"
		:class="{active: isActive}"
		class="app-content-list-item"
		:style="indentItem">
		<div class="app-content-list-item-icon"
			:style="indentIcon">
			<slot name="icon">
				<div :style="iconStyle">
					{{ firstGrapheme }}
				</div>
			</slot>
		</div>
		<div class="app-content-list-item-line-one">
			{{ title }}
		</div>
		<div v-if="$scopedSlots['line-two']"
			class="app-content-list-item-line-two">
			<slot name="line-two" />
		</div>
		<Actions class="app-content-list-item-details">
			<slot name="actions" />
		</Actions>
	</router-link>
</template>

<script>

import Actions from '@nextcloud/vue/dist/Components/Actions'
import { mapGetters } from 'vuex'

export default {
	name: 'PagesListItem',

	components: {
		Actions,
	},

	props: {
		to: {
			type: String,
			default: '',
		},
		title: {
			type: String,
			required: true,
		},
		indent: {
			type: Number,
			default: 0,
		},
		pageId: {
			type: Number,
			default: 0,
		},
	},

	computed: {
		...mapGetters([
			'currentPage',
		]),

		isActive() {
			return this.$store.state.route.path === this.to
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
	},
}

</script>

<style lang="scss" scoped>

	.app-content-list-item .app-content-list-item-icon {
		line-height: 40px;
		width: 30px;
		left: 12px;
	}

	.app-content-list-item .app-content-list-item-icon div {
		border-radius: 3px 12px 3px 3px;
	}

	.app-content-list .app-content-list-item .app-content-list-item-line-two {
		opacity: 1;
	}

	div.app-content-list-item:hover {
		background-color: var(--color-main-background);
	}

	div.app-content-list-item {
		cursor: default;
	}

</style>
