<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContentDetails>
		<div v-if="loading('pagelist') || loading('currentPage') || !pagesLoaded" class="sheet-view">
			<SkeletonLoading :count="1" class="page-heading-skeleton" type="page-heading" />
		</div>
		<PageVersion v-else-if="currentPage && selectedVersion" />
		<PageContainer v-else-if="currentPage" />
		<PageNotFound v-else />

		<NcPopover
			v-if="!networkOnline"
			:aria-label="t('collectives', 'Offline')"
			:auto-hide="false"
			no-focus-trap
			class="offline-indicator">
			<template #trigger>
				<NcButton
					variant="tertiary"
					:aria-label="t('collectives', 'Offline')"
					class="trigger offline-indicator__button"
					:class="{ mobile: isMobile, desktop: !isMobile }">
					<template #icon>
						<span class="offline-indicator__dot" />
					</template>
					{{ offlineIndicatorText }}
				</NcButton>
			</template>
			<div class="offline-indicator__hint">
				<span>{{ t('collectives', 'Offline') }}</span>
			</div>
		</NcPopover>
	</NcAppContentDetails>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { NcAppContentDetails, NcButton, NcPopover } from '@nextcloud/vue'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import PageNotFound from './Page/PageNotFound.vue'
import PageContainer from './PageContainer.vue'
import PageVersion from './PageVersion.vue'
import SkeletonLoading from './SkeletonLoading.vue'
import { useNetworkState } from '../composables/useNetworkState.ts'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useSharesStore } from '../stores/shares.js'
import { useTagsStore } from '../stores/tags.js'
import { useVersionsStore } from '../stores/versions.js'
import displayError from '../util/displayError.js'

export default {
	name: 'CollectiveContainer',

	components: {
		NcAppContentDetails,
		NcButton,
		NcPopover,
		PageContainer,
		PageNotFound,
		PageVersion,
		SkeletonLoading,
	},

	setup() {
		const isMobile = useIsMobile()
		const { networkOnline } = useNetworkState()
		return { isMobile, networkOnline }
	},

	data() {
		return {
			loadPending: true,
		}
	},

	computed: {
		...mapState(useRootStore, ['isPublic', 'loading']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectivePath',
		]),

		...mapState(usePagesStore, [
			'currentFileIdPage',
			'currentPage',
			'currentPagePath',
			'isLandingPage',
			'pagePath',
			'pagesLoaded',
		]),

		...mapState(useVersionsStore, ['selectedVersion']),

		notFound() {
			return !this.loading('pagelist') && !this.loading('currentPage') && !this.currentPage
		},

		offlineIndicatorText() {
			return this.isMobile
				? ''
				: t('collectives', 'Offline')
		},
	},

	watch: {
		'currentCollective.id': function(val) {
			this.clearFilterTags()
			if (val) {
				this.initCollective()
			}
		},

		'currentPage.id': function() {
			this.selectVersion(null)
			this.slugUrl()
		},

		notFound: function(current) {
			if (current && this.currentFileIdPage) {
				this.$router.replace(this.pagePath(this.currentFileIdPage) + document.location.hash)
			}
		},

		networkOnline: function(val) {
			if (val && this.loadPending) {
				this.getShares()
			}
		},
	},

	mounted() {
		this.initCollective()
		this.slugUrl()
	},

	methods: {
		...mapActions(useRootStore, ['show']),
		...mapActions(useSharesStore, ['getShares']),
		...mapActions(useTagsStore, ['clearFilterTags']),
		...mapActions(useVersionsStore, ['selectVersion']),

		async initCollective() {
			this.closeNav()
			this.show('details')

			this.loadPending = true
			if (!this.networkOnline) {
				return
			}

			if (!this.isPublic) {
				try {
					await this.getShares()
					this.loadPending = false
				} catch (e) {
					displayError('Could not fetch shares')(e)
				}
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

<style scoped lang="scss">
.offline-indicator {
	position: absolute;
	z-index: 100010;
	bottom: calc(var(--default-grid-baseline) * 3);
	margin-left: calc(var(--default-grid-baseline) * 3);
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-element);

	&__button {
		&.mobile {
			// Same padding left and right on mobile without text
			padding-inline: var(--default-grid-baseline);
		}

		&.desktop {
			// No tooltip on desktop
			pointer-events: none;
		}
	}

	&__dot {
		display: inline-block;
		height: 12px;
		width: 12px;
		background-color: var(--color-element-error, var(--color-error));
		border-radius: 50%;
	}

	&__hint {
		padding: 12px;
		max-width: 300px;
		text-align: start;
	}
}
</style>

<style lang="scss">
.app-content-details {
	// Required for search dialog to stick to the bottom
	height: 100%;
}

.page-heading-skeleton {
	width: 100%;
}

/* Format page title in PageContainer.vue and PageVersion.vue */
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
