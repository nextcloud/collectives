<template>
	<div>
		<PagesListItem key="page.title"
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
				<ActionButton class="primary"
					icon="icon-add"
					@click="newPage(page)">
					{{ t('collectives', 'Add a subpage') }}
				</ActionButton>
			</template>
		</PagesListItem>
		<SubPagesList v-for="subpage in subpagesView"
			:key="subpage.id"
			:page="subpage"
			:level="level+1" />
	</div>
</template>

<script>

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import LastUpdate from './LastUpdate'
import PagesListItem from './PagesListItem'

import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import { NEW_PAGE } from '../store/actions'

export default {
	name: 'SubPagesList',

	components: {
		ActionButton,
		LastUpdate,
		PagesListItem,
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
	},

	data() {
		return {
			collapsed: true,
		}
	},

	computed: {
		...mapGetters([
			'collectiveParam',
			'loading',
			'currentPagePath',
			'mostRecentSubpages',
		]),

		subpages() {
			return this.mostRecentSubpages(this.page.id)
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
				filePath: [encodeURI(parentPage.filePath), encodeURIComponent(parentPage.title)].filter(Boolean).join('/'),
				parentId: parentPage.id,
			}
			try {
				await this.$store.dispatch(NEW_PAGE, page)
				this.$router.push(this.$store.getters.updatedPagePath)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
		},

		toggleCollapsed() {
			this.collapsed = !this.collapsed
		},

		initCollapsed() {
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
