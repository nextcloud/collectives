<template>
	<div>
		<Item v-show="pageInFilterString"
			key="page.title"
			:to="pagePath(page)"
			:page-id="page.id"
			:parent-page-id="page.parentId"
			:title="page.title"
			:timestamp="page.timestamp"
			:last-user-id="page.lastUserId"
			:emoji="page.emoji"
			:level="level"
			:can-edit="currentCollectiveCanEdit"
			:is-template="isTemplate"
			:has-visible-subpages="hasVisibleSubpages"
			:filtered-view="filterString !== ''"
			@toggleCollapsed="toggleCollapsed(page.id)"
			@click.native="show('details')" />
		<SubpageList v-if="templateView"
			:key="templateView.id"
			:page="templateView"
			:level="level+1"
			:filter-string="filterString"
			:is-template="true" />
		<SubpageList v-for="subpage in subpagesView"
			:key="subpage.id"
			:page="subpage"
			:level="level+1"
			:filter-string="filterString"
			:is-template="isTemplate" />
	</div>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import Item from './Item.vue'

export default {
	name: 'SubpageList',

	components: {
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
		filterString: {
			type: String,
			default: '',
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
			'currentPages',
			'templatePage',
			'visibleSubpages',
			'collapsed',
			'showTemplates',
		]),

		pageInFilterString() {
			return this.page.title.toLowerCase().includes(this.filterString.toLowerCase())
		},

		showSubpages() {
			// Display subpages if either in filtered view or not collapsed
			return this.filterString !== '' || !this.collapsed(this.page.id)
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
			'expand',
			'toggleCollapsed',
			'show',
		]),

		initCollapsed() {
			// Expand subpages if they're in the path to currentPage
			if (this.currentPages.includes(this.page)) {
				this.expand(this.page.id)
			}
		},
	},
}

</script>
