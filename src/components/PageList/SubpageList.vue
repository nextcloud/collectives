<template>
	<div>
		<Item v-show="pageInFilterString"
			key="page.title"
			:to="pagePath(page)"
			:has-children="hasChildren"
			:page-id="page.id"
			:level="level"
			:filtered-view="filterString !== ''"
			:title="page.title"
			:is-template="isTemplate"
			@toggleCollapsed="toggleCollapsed(page.id)"
			@click.native="show('details')">
			<template v-if="currentCollectiveCanEdit" #actions>
				<ActionButton icon="icon-add"
					:close-after-click="true"
					@click="newPage(page.id)">
					{{ t('collectives', 'Add a subpage') }}
				</ActionButton>
				<ActionButton v-if="showTemplates && !isTemplate"
					class="action-button__template"
					:close-after-click="true"
					@click="editTemplate(page.id)">
					<template #icon>
						<PagesTemplateIcon :size="14" />
					</template>
					{{ editTemplateString }}
				</ActionButton>
			</template>
		</Item>
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

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Item from './Item.vue'
import PagesTemplateIcon from '../Icon/PagesTemplateIcon.vue'

import { mapGetters, mapMutations } from 'vuex'
import pageMixin from '../../mixins/pageMixin.js'

export default {
	name: 'SubpageList',

	components: {
		ActionButton,
		Item,
		PagesTemplateIcon,
	},

	mixins: [
		pageMixin,
	],

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

		considerTemplate() {
			// Consider template in view if it exists and templates view is true
			return this.templatePage(this.page.id) && this.showTemplates
		},

		templateView() {
			if (this.considerTemplate && this.showSubpages) {
				return this.templatePage(this.page.id)
			}
			return null
		},

		hasChildren() {
			return !!this.visibleSubpages(this.page.id).length || this.considerTemplate
		},

		editTemplateString() {
			if (this.templateView) {
				return t('collectives', 'Edit template for subpages')
			} else {
				return t('collectives', 'Add template for subpages')
			}
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

<style lang="scss" scoped>
.app-content-list {
	padding-top: 40px;
}
</style>
