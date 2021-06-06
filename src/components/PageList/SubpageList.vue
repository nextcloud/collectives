<template>
	<div>
		<Item key="page.title"
			:to="`/${encodeURIComponent(collectiveParam)}/${pagePath}`"
			:collapsible="isCollapsible"
			:page-id="page.id"
			:level="level"
			:title="page.title"
			:collapsed="collapsed"
			@toggleCollapsed="toggleCollapsed"
			@click.native="show('details')">
			<template #line-two>
				<LastUpdate :timestamp="page.timestamp"
					:user="page.lastUserId" />
			</template>
			<template #actions>
				<ActionButton
					icon="icon-add"
					@click="newPage(page)">
					{{ t('collectives', 'Add a subpage') }}
				</ActionButton>
			</template>
		</Item>
		<SubpageList v-for="subpage in subpagesView"
			:key="subpage.id"
			:page="subpage"
			:sort-order="sortOrder"
			:level="level+1" />
	</div>
</template>

<script>

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import LastUpdate from './LastUpdate'
import Item from './Item'

import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import { NEW_PAGE, GET_PAGES } from '../../store/actions'

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
		sortOrder: {
			type: Function,
			required: true,
		},
	},

	data() {
		return {
			collapsed: true,
		}
	},

	computed: {
		...mapGetters([
			'pageParam',
			'collectiveParam',
			'loading',
			'currentPagePath',
			'visibleSubpages',
		]),

		subpages() {
			return this.visibleSubpages(this.page.id)
				.sort(this.sortOrder)
		},

		subpagesView() {
			// Only display subpages if not collapsed
			if (this.collapsed) {
				return []
			} else {
				return this.subpages
			}
		},

		isCollapsible() {
			return !!this.subpages.length
		},

		/**
		 * Path to the page
		 * @returns {string}
		 */
		pagePath() {
			const parts = this.page.filePath.split('/')
			if (this.page.fileName !== 'Readme.md') {
				parts.push(this.page.title)
			}
			return parts
				.filter(Boolean)
				.map(p => encodeURIComponent(p))
				.join('/')
		},
	},

	watch: {
		// Reinitate collapsed state when route changes (to expand currentPage if applicable)
		'pageParam'() {
			this.initCollapsed()
		},
	},

	mounted() {
		this.initCollapsed()
	},

	methods: {
		...mapMutations(['show']),

		/**
		 * Create a new page and focus the page automatically
		 * @param {Object} parentPage Parent page
		 */
		async newPage(parentPage) {
			const page = {
				title: t('collectives', 'New Page'),
				filePath: [parentPage.filePath, parentPage.title].filter(Boolean).join('/'),
				parentId: parentPage.id,
			}
			try {
				await this.$store.dispatch(NEW_PAGE, page)
				this.collapsed = false
				this.$router.push(this.$store.getters.updatedPagePath)
				// The parents location changes when the first subpage
				// is created.
				this.$store.dispatch(GET_PAGES)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
		},

		toggleCollapsed() {
			this.collapsed = !this.collapsed
		},

		initCollapsed() {
			// Expand subpages if they're in the path to currentPage
			if (this.currentPagePath.includes(this.page)) {
				this.collapsed = false
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
