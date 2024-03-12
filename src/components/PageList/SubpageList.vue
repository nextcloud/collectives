<template>
	<div>
		<Item :key="page.title"
			:to="pagePath(page)"
			:page-id="page.id"
			:parent-id="page.parentId"
			:title="page.title"
			:timestamp="page.timestamp"
			:last-user-id="page.lastUserId"
			:last-user-display-name="page.lastUserDisplayName"
			:emoji="page.emoji"
			:level="level"
			:can-edit="currentCollectiveCanEdit"
			:is-template="isTemplate"
			:has-visible-subpages="hasVisibleSubpages"
			:filtered-view="filteredView"
			@toggleCollapsed="toggleCollapsed(page.id)"
			@click.native="show('details')" />
		<div class="page-list-indent">
			<SubpageList v-if="templateView"
				:key="templateView.id"
				:page="templateView"
				:level="level+1"
				:is-template="true" />
			<Draggable v-if="subpagesView.length > 0 || keptSortable(page.id)"
				:list="subpagesView"
				:parent-id="page.id"
				:disable-sorting="disableSorting"
				:is-template="isTemplate">
				<SubpageList v-for="subpage in subpagesView"
					:key="subpage.id"
					:data-page-id="subpage.id"
					:page="subpage"
					:level="level+1"
					:is-template="isTemplate"
					class="page-list-drag-item" />
			</Draggable>
		</div>
	</div>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import Draggable from './Draggable.vue'
import Item from './Item.vue'

export default {
	name: 'SubpageList',

	components: {
		Draggable,
		Item,
	},

	props: {
		page: {
			type: Object,
			required: true,
		},
		level: {
			type: Number,
			required: true,
		},
		filteredView: {
			type: Boolean,
			default: false,
		},
		isTemplate: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		...mapGetters([
			'currentCollectiveCanEdit',
			'pageParam',
			'pagePath',
			'currentPageIds',
			'keptSortable',
			'templatePage',
			'visibleSubpages',
			'collapsed',
			'showTemplates',
		]),

		showSubpages() {
			// Display subpages only when not in filtered view and when not collapsed
			return !this.filteredView && !this.collapsed(this.page.id)
		},

		subpagesView() {
			if (this.showSubpages) {
				return this.visibleSubpages(this.page.id)
			}
			return []
		},

		hasTemplate() {
			return !!this.templatePage(this.page.id)
		},

		considerTemplate() {
			// Consider template in view if we show templates and we have one
			return this.showTemplates && this.hasTemplate
		},

		templateView() {
			if (this.considerTemplate && this.showSubpages) {
				return this.templatePage(this.page.id)
			}
			return null
		},

		hasVisibleSubpages() {
			return !!this.visibleSubpages(this.page.id).length || this.considerTemplate
		},

		disableSorting() {
			return this.filteredView
		},
	},

	watch: {
		// Reinitate collapsed state when route changes
		'pageParam'() {
			this.initCollapsed()
		},
	},

	mounted() {
		this.initCollapsed()
	},

	methods: {
		...mapMutations([
			'collapse',
			'expand',
			'toggleCollapsed',
			'show',
		]),

		initCollapsed() {
			// Expand subpages if they're in the path to currentPage
			if (this.currentPageIds.includes(this.page.id)) {
				this.expand(this.page.id)
			}
		},
	},
}

</script>

<style>
.page-list-indent {
	padding-left: 28px;
}
</style>
