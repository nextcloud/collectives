<template>
	<div>
		<Item key="page.title"
			:to="pagePath(page)"
			:collapsible="isCollapsible"
			:page-id="page.id"
			:level="level"
			:title="page.title"
			:collapsed="collapsed(page.id)"
			:is-template="isTemplate"
			@toggleCollapsed="toggleCollapsed(page.id)"
			@click.native="show('details')">
			<template #line-two>
				<LastUpdate :timestamp="page.timestamp"
					:user="page.lastUserId" />
			</template>
			<template v-if="!isPublic" #actions>
				<ActionButton
					icon="icon-add"
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
			:is-template="true" />
		<SubpageList v-for="subpage in subpagesView"
			:key="subpage.id"
			:page="subpage"
			:level="level+1"
			:is-template="isTemplate" />
	</div>
</template>

<script>

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import LastUpdate from './LastUpdate'
import Item from './Item'

import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
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
		isTemplate: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		...mapGetters([
			'isPublic',
			'pageParam',
			'collectiveParam',
			'pagePath',
			'currentPages',
			'templatePage',
			'visibleSubpages',
			'collapsed',
			'showTemplates',
		]),

		templateView() {
			// Only display if not collapsed
			if (!this.showTemplates || this.collapsed(this.page.id)) {
				return null
			} else {
				return this.templatePage(this.page.id)
			}
		},

		subpages() {
			return this.visibleSubpages(this.page.id)
		},

		subpagesView() {
			// Only display subpages if not collapsed
			if (this.collapsed(this.page.id)) {
				return []
			} else {
				return this.subpages
			}
		},

		isCollapsible() {
			return !!this.subpages.length
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
		...mapMutations(['collapse', 'expand', 'toggleCollapsed', 'show']),

		/**
		 * Open existing or create new template page
		 *
		 * @param {object} parentPage Parent page
		 */
		async editTemplate(parentPage) {
			if (this.templatePage(parentPage.id)) {
				this.$router.push(this.pagePath(this.templatePage(parentPage.id)))
				return
			}

			try {
				await this.$store.dispatch(NEW_TEMPLATE, parentPage)
				this.$router.push(this.$store.getters.newPagePath)
				this.expand(this.page.id)
				// The parents location changes when the first subpage
				// is created.
				this.$store.dispatch(GET_PAGES)
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
				this.$router.push(this.$store.getters.newPagePath)
				this.expand(this.page.id)
				// The parents location changes when the first subpage
				// is created.
				this.$store.dispatch(GET_PAGES)
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
