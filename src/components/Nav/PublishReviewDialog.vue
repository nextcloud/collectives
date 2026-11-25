<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		class="publish-dialog publish-review-dialog"
		:name="name"
		:buttons="buttons"
		@closing="$emit('close')">
		<NcNoteCard
			v-if="lastFailureReason"
			type="error"
			:text="lastFailureReason" />
		<template v-if="isRetry">
			<!-- eslint-disable-next-line vue/no-v-html -- translation is sanitized, the address is escaped -->
			<p class="publish-review-dialog__text" v-html="goesOnlineText" />
			<ul class="publish-review-dialog__pages">
				<li v-for="page in pagesToPublish" :key="page.id" class="publish-review-dialog__page">
					<a :href="pageUrl(page)" target="_blank" rel="noopener noreferrer">{{ pageTitle(page) }}</a>
				</li>
			</ul>
		</template>
		<template v-else>
			<!-- eslint-disable-next-line vue/no-v-html -- translation is sanitized, the URL is escaped -->
			<p class="publish-review-dialog__text" v-html="publishedAgainText" />
			<ul v-if="changes.changedPages.length" class="publish-review-dialog__pages">
				<li v-for="page in changes.changedPages" :key="page.id" class="publish-review-dialog__page">
					<a :href="pageUrl(page)" target="_blank" rel="noopener noreferrer">{{ pageTitle(page) }}</a>
					<span class="publish-review-dialog__time" :title="formatAbsoluteTime(page.timestamp)">
						{{ formatRelativeTime(page.timestamp) }}
					</span>
				</li>
			</ul>
			<p v-if="changes.deletedPageCount" class="publish-review-dialog__text">
				{{ n('collectives', '%n published page was deleted and gets removed from the website.', '%n published pages were deleted and get removed from the website.', changes.deletedPageCount) }}
			</p>
		</template>
	</NcDialog>
</template>

<script>
import { n, t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import escapeHtml from 'escape-html'
import { mapActions, mapState } from 'pinia'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { usePagesStore } from '../../stores/pages.js'
import { useStaticSitesStore } from '../../stores/staticSites.js'
import displayError from '../../util/displayError.js'
import { STATIC_SITE_ADDRESS_PREFIX } from '../../util/staticSiteSlug.js'
import { staticSiteFailureReason, staticSiteState, unpublishedChanges } from '../../util/staticSiteStatus.js'

export default {
	name: 'PublishReviewDialog',

	components: {
		NcDialog,
		NcNoteCard,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},

		/** Static site to publish again with its current settings, those of the last attempt if it failed */
		staticSite: {
			type: Object,
			required: true,
		},

		/** All pages of the collective */
		pages: {
			type: Array,
			required: true,
		},
	},

	emits: [
		'close',
	],

	computed: {
		...mapState(usePagesStore, ['pagePathInCollective']),

		// Otherwise the website is online, but published pages changed since
		isRetry() {
			return staticSiteState(this.staticSite) === 'failed'
		},

		name() {
			return this.isRetry
				? t('collectives', 'Retry publishing website "{title}"?', { title: this.staticSite.title })
				: t('collectives', 'Update website "{title}"?', { title: this.staticSite.title })
		},

		lastFailureReason() {
			return this.isRetry
				? t('collectives', 'The last attempt failed {time}: {reason}', {
						time: this.formatRelativeTime(this.staticSite.updatedAt),
						reason: staticSiteFailureReason(this.staticSite),
					})
				: null
		},

		// Deleted pages can't be published anymore, the backend adds the ancestors of the others
		pageIdsToPublish() {
			const existingPageIds = new Set(this.pages.map(({ id }) => id))
			const rootPageId = this.pages.find(({ parentId }) => parentId === 0)?.id
			const pageIds = new Set(this.staticSite.selectedPageIds.filter((id) => existingPageIds.has(id)))
			if (rootPageId) {
				pageIds.add(rootPageId)
			}
			return [...pageIds]
		},

		pagesToPublish() {
			const pageIds = new Set(this.pageIdsToPublish)
			return this.pages.filter(({ id }) => pageIds.has(id))
		},

		changes() {
			return unpublishedChanges(this.staticSite, this.pages)
		},

		goesOnlineText() {
			const address = this.staticSite.publishedUrl ?? STATIC_SITE_ADDRESS_PREFIX + this.staticSite.slug
			return n('collectives', 'This page goes online at {address}:', 'These %n pages go online at {address}:', this.pagesToPublish.length, {
				address: { value: `<strong>${escapeHtml(address)}</strong>`, escape: false },
			})
		},

		publishedAgainText() {
			const url = { value: `<strong>${escapeHtml(this.staticSite.publishedUrl)}</strong>`, escape: false }
			if (!this.changes.changedPages.length) {
				return t('collectives', 'The whole website at {url} is published again.', { url })
			}
			return t('collectives', 'The whole website at {url} is published again. These pages changed since it got published {time}:', {
				url,
				time: this.formatRelativeTime(this.staticSite.publishedAt),
			})
		},

		buttons() {
			return [
				{
					label: t('collectives', 'Cancel'),
					variant: 'secondary',
				},
				{
					label: t('collectives', 'Publish'),
					variant: 'primary',
					// Without the pages, the home page and deleted pages are unknown
					disabled: !this.pages.length,
					callback: () => this.onPublish(),
				},
			]
		},
	},

	methods: {
		n,
		t,

		...mapActions(useStaticSitesStore, ['getStaticSites', 'publishStaticSite']),

		pageTitle(page) {
			return page.parentId === 0 ? t('collectives', 'Home') : page.title
		},

		pageUrl(page) {
			return generateUrl('/apps/collectives' + this.pagePathInCollective(this.collective, page))
		},

		formatRelativeTime(timestamp) {
			return moment.unix(timestamp).fromNow()
		},

		formatAbsoluteTime(timestamp) {
			return moment.unix(timestamp).format('LLL')
		},

		/**
		 * @return {Promise<boolean|undefined>} false to keep the dialog open after an error
		 */
		async onPublish() {
			try {
				await this.publishStaticSite(this.collective, {
					id: this.staticSite.id,
					pageIds: this.pageIdsToPublish,
					title: this.staticSite.title,
					slug: this.staticSite.slug,
					theme: this.staticSite.theme,
				})
			} catch (e) {
				displayError('Could not publish collective as website')(e)
				return false
			} finally {
				// Also shows a website the backend stored as failed or one that changed meanwhile
				this.getStaticSites(this.collective.id)
					.catch(displayError('Could not load static sites for collective'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.publish-review-dialog {
	&__text {
		margin-block-end: 8px;
	}

	&__pages {
		max-height: 240px;
		overflow-y: auto;
		margin-block-end: 8px;
		padding-block: 4px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		background-color: var(--color-background-hover);
	}

	&__page {
		display: flex;
		align-items: center;
		gap: 12px;
		padding-block: 4px;
		padding-inline: 12px;

		a {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			text-decoration: underline;
		}
	}

	&__time {
		flex: 0 0 auto;
		margin-inline-start: auto;
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
	}
}
</style>

<style lang="scss">
@use '../../css/publish-dialog.scss';
</style>
