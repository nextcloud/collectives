<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContentDetails>
		<div v-if="loading('pagelist') || loading('currentPage')" class="sheet-view">
			<SkeletonLoading :count="1" class="page-heading-skeleton" type="page-heading" />
		</div>
		<PageVersion v-else-if="currentPage && selectedVersion" />
		<Page v-else-if="currentPage" />
		<PageNotFound v-else />
	</NcAppContentDetails>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { useSharesStore } from '../stores/shares.js'
import { useTagsStore } from '../stores/tags.js'
import { usePagesStore } from '../stores/pages.js'
import { useVersionsStore } from '../stores/versions.js'
import { emit } from '@nextcloud/event-bus'
import { NcAppContentDetails } from '@nextcloud/vue'
import displayError from '../util/displayError.js'
import Page from './Page.vue'
import PageVersion from './PageVersion.vue'
import PageNotFound from './Page/PageNotFound.vue'
import SkeletonLoading from './SkeletonLoading.vue'

export default {
	name: 'Collective',

	components: {
		SkeletonLoading,
		NcAppContentDetails,
		Page,
		PageNotFound,
		PageVersion,
	},

	computed: {
		...mapState(useRootStore, ['isPublic', 'loading', 'pageParam', 'pageId']),
		...mapState(useCollectivesStore, [
			'collectivePath',
			'currentCollective',
			'currentCollectivePath',
		]),
		...mapState(usePagesStore, [
			'currentFileIdPage',
			'currentPage',
			'isLandingPage',
			'pagePath',
			'currentPagePath',
		]),
		...mapState(useVersionsStore, ['selectedVersion']),

		notFound() {
			return !this.loading('pagelist') && !this.loading('currentPage') && !this.currentPage
		},
	},

	watch: {
		'currentCollective.id'(val) {
			this.clearFilterTags()
			if (val) {
				this.initCollective()
			}
		},
		'currentPage.id'() {
			this.selectVersion(null)
			this.slugUrl()
		},
		'notFound'(current) {
			if (current && this.currentFileIdPage) {
				this.$router.replace(this.pagePath(this.currentFileIdPage) + document.location.hash)
			}
		},
	},

	mounted() {
		this.initCollective()
		this.slugUrl()
	},

	methods: {
		...mapActions(useRootStore, ['hide', 'load', 'show']),
		...mapActions(useSharesStore, ['getShares']),
		...mapActions(useTagsStore, ['clearFilterTags']),
		...mapActions(useVersionsStore, ['selectVersion']),

		initCollective() {
			this.closeNav()
			this.show('details')

			if (!this.isPublic) {
				this.getShares()
					.catch(displayError('Could not fetch shares'))
			}
		},

		closeNav() {
			emit('toggle-navigation', { open: false })
		},

		slugUrl() {
			// Redirect to slugified URL if possible
			if (this.currentCollective
				&& this.isLandingPage
				&& this.$route.path !== this.currentCollectivePath) {
				this.$router.replace({ path: this.currentCollectivePath, hash: document.location.hash })
			} else if (this.currentPage
				&& this.$route.path !== this.currentPagePath) {
				this.$router.replace({ path: this.currentPagePath, hash: document.location.hash })
			}
		},
	},

}
</script>

<style lang="scss">
.app-content-details {
	// Required for search dialog to stick to the bottom
	height: 100%;
}

.page-heading-skeleton {
	width: 100%;
}

/* Format page title in Page.vue and PageVersion.vue */
.page-title {
	position: relative;
	z-index: 10022;
	padding: 0 8px;
}

// Align sidebar toggle
.app-sidebar__toggle {
	inset-block-start: 7px !important;
}

@media print {
	/* Don't print splitpane list and splitter panes */
	div.splitpanes__pane-list, div.splitpanes__splitter {
		display: none !important;
	}

	/* Don't print page list, list toggle and page sidebar toggle */
	#app-sidebar-vue, .app-navigation, .app-sidebar__toggle {
		display: none !important;
	}

	div.splitpanes__pane-details {
		width: unset !important;
	}
}
</style>
