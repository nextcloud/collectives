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
			<template #line-two>
				<LastUpdate :timestamp="page.timestamp"
					:user="page.lastUserId" />
			</template>
			<template v-if="currentCollectiveCanEdit" #actions>
				<ActionButton icon="icon-add"
					@click="newPage(page)">
					{{ t('collectives', 'Add a subpage') }}
				</ActionButton>
				<ActionButton v-if="showTemplates && !isTemplate"
					icon="icon-pages-template-dark-grey"
					class="action-button__template"
					@click="editTemplate(page)">
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
import LastUpdate from './LastUpdate'
import Item from './Item'

import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations, mapState } from 'vuex'
import { NEW_PAGE, NEW_TEMPLATE, GET_PAGES } from '../../store/actions'

export default {
	name: 'SubpageList',

	components: {
		ActionButton,
		LastUpdate,
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
		...mapState({
			newPageId: (state) => state.pages.newPage.id,
		}),

		...mapGetters([
			'currentCollectiveCanEdit',
			'pageParam',
			'collectiveParam',
			'pagePath',
			'newPagePath',
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
			'collapse',
			'expand',
			'toggleCollapsed',
			'scrollToPage',
			'show',
		]),

		/**
		 * Open existing or create new template page
		 *
		 * @param {object} parentPage Parent page
		 */
		async editTemplate(parentPage) {
			const templatePage = this.templatePage(this.page.id)
			if (templatePage) {
				this.$router.push(this.pagePath(templatePage))
				if (this.showTemplates) {
					this.$nextTick(() => this.scrollToPage(templatePage.id))
				}
				return
			}

			try {
				await this.$store.dispatch(NEW_TEMPLATE, parentPage)
				this.$router.push(this.newPagePath)
				this.expand(this.page.id)
				// The parents location changes when the first subpage
				// is created.
				this.$store.dispatch(GET_PAGES)
				if (this.showTemplates) {
					this.$nextTick(() => this.scrollToPage(this.newPageId))
				}
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
		},

		/**
		 * Create a new page and focus the page automatically
		 *
		 * @param {object} parentPage Parent page
		 */
		async newPage(parentPage) {
			const page = {
				title: t('collectives', 'New Page'),
				parentId: parentPage.id,
			}
			try {
				await this.$store.dispatch(NEW_PAGE, page)
				this.$router.push(this.newPagePath)
				this.expand(this.page.id)
				// The parents location changes when the first subpage
				// is created.
				this.$store.dispatch(GET_PAGES)
				this.$nextTick(() => this.scrollToPage(this.newPageId))
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
		},

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

// template icon appears too big with default size (16px)
.action-button__template::v-deep .icon-pages-template-dark-grey {
	background-size: 14px;
}
</style>
