<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		size="normal"
		class="collective-publish-modal"
		@close="onClose">
		<div class="modal-publish" @keydown.esc="onEscape">
			<h2 class="modal-publish__name">
				{{ t('collectives', 'Publish website for collective {name}', { name: collective.name }) }}
			</h2>
			<div v-if="staticSites.length" class="modal-publish__sites">
				<div class="modal-publish__sites-header">
					<h3>{{ t('collectives', 'Websites') }}</h3>
					<NcButton
						:variant="selectedStaticSite ? 'secondary' : 'primary'"
						:disabled="loadingPages"
						@click="selectNewStaticSite">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('collectives', 'New website') }}
					</NcButton>
				</div>
				<ul class="modal-publish__site-list">
					<li
						v-for="staticSite in staticSites"
						:key="staticSite.id"
						class="modal-publish__site"
						:class="{ 'modal-publish__site--selected': staticSite.id === selectedStaticSiteId }">
						<button
							type="button"
							class="modal-publish__site-select"
							:aria-pressed="staticSite.id === selectedStaticSiteId ? 'true' : 'false'"
							:disabled="loadingPages"
							@click="selectStaticSite(staticSite)">
							<strong>{{ staticSite.title }}</strong>
							<span>{{ staticSite.slug }} · {{ staticSiteStatusLabel(staticSite) }}</span>
						</button>
						<a
							v-if="staticSite.publishedUrl"
							:href="staticSite.publishedUrl"
							target="_blank"
							rel="noopener noreferrer">{{ staticSite.publishedUrl }}</a>
						<p v-if="staticSite.status === 'failed'" class="modal-publish__site-error">
							{{ staticSiteErrorMessage(staticSite.lastError) }}
							<template v-if="staticSite.publishedUrl">
								{{ t('collectives', 'The previous version is still online.') }}
							</template>
						</p>
						<span v-if="staticSite.publishedAt" class="modal-publish__site-published-at">
							{{ t('collectives', 'Last published {date}', { date: formatTimestamp(staticSite.publishedAt) }) }}
						</span>
					</li>
				</ul>
			</div>
			<NcNoteCard v-if="isSelectedStaticSiteInProgress" type="info">
				{{ t('collectives', 'This website is being published. You can update it once publishing finished.') }}
			</NcNoteCard>
			<div class="modal-publish__fields">
				<NcTextField
					v-model="title"
					:label="t('collectives', 'Website title')"
					:error="!isTitleValid"
					:disabled="loadingPages"
					:maxlength="titleMaxLength" />
				<!-- TRANSLATORS The web address is the part of the website URL that identifies the published collective -->
				<NcTextField
					v-model="slug"
					:label="t('collectives', 'Web address')"
					:error="!isSlugValid"
					:disabled="loadingPages || !!selectedStaticSite"
					:helperText="slugHelperText"
					:maxlength="slugMaxLength" />
			</div>
			<NcLoadingIcon v-if="loadingPages" class="modal-publish__loading" :size="32" />
			<ul v-else class="modal-publish__tree">
				<li v-if="rootPage" class="modal-publish__collective-row">
					<PublishTreeRow
						:selected="isRootPageSelected"
						@update:selected="onToggleSelect(rootPage.id)">
						<template #icon>
							<span v-if="collective.emoji">{{ collective.emoji }}</span>
							<PageTemplateIcon v-else :size="20" fillColor="var(--color-text-maxcontrast)" />
						</template>
						<strong>{{ collective.name }}</strong>
					</PublishTreeRow>
				</li>
				<PublishPageTreeItem
					v-for="page in topLevelPages"
					:key="page.id"
					:collective="collective"
					:page="page"
					:depth="1"
					:selectedPageIds="selectedPageIds"
					:expandedPageIds="expandedPageIds"
					@toggleSelect="onToggleSelect"
					@toggleExpand="onToggleExpand" />
			</ul>
			<div class="modal-publish__footer">
				<NcButton
					variant="primary"
					:loading="publishing"
					:disabled="loadingPages || publishing || isSelectedStaticSiteInProgress || !isTitleValid || !isSlugValid"
					@click="onPublishAsWebsite">
					{{ selectedStaticSite ? t('collectives', 'Update website') : t('collectives', 'Publish website') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { mapActions, mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import PublishPageTreeItem from './PublishPageTreeItem.vue'
import PublishTreeRow from './PublishTreeRow.vue'
import { createStaticSite, updateStaticSite } from '../../apis/collectives/index.js'
import { usePagesStore } from '../../stores/pages.js'
import { useStaticSitesStore } from '../../stores/staticSites.js'
import displayError from '../../util/displayError.js'
import { staticSiteErrorMessage } from '../../util/staticSiteError.js'
import { generateSlug, isValidSlug, SLUG_MAX_LENGTH } from '../../util/staticSiteSlug.js'

const TITLE_MAX_LENGTH = 255

export default {
	name: 'CollectivePublishModal',

	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcNoteCard,
		NcTextField,
		PageTemplateIcon,
		PlusIcon,
		PublishPageTreeItem,
		PublishTreeRow,
	},

	props: {
		collective: {
			required: true,
			type: Object,
		},
	},

	emits: [
		'close',
	],

	data() {
		return {
			selectedPageIds: new Set(),
			expandedPageIds: new Set(),
			loadingPages: true,
			publishing: false,
			/** @type {?number} null for a new website */
			selectedStaticSiteId: null,
			title: this.collective.name,
			slug: generateSlug(this.collective.name),
			titleMaxLength: TITLE_MAX_LENGTH,
			slugMaxLength: SLUG_MAX_LENGTH,
		}
	},

	computed: {
		...mapState(usePagesStore, [
			'pagesForCollective',
			'pagesTreeWalkForCollective',
			'visibleSubpagesForCollective',
		]),

		...mapState(useStaticSitesStore, ['isStaticSiteInProgress', 'staticSitesForCollective']),

		staticSites() {
			return this.staticSitesForCollective(this.collective.id)
		},

		selectedStaticSite() {
			return this.staticSites.find(({ id }) => id === this.selectedStaticSiteId) ?? null
		},

		isSelectedStaticSiteInProgress() {
			return !!this.selectedStaticSite && this.isStaticSiteInProgress(this.selectedStaticSite)
		},

		allPageIds() {
			return this.pagesTreeWalkForCollective(this.collective).map((page) => page.id)
		},

		rootPage() {
			return this.pagesForCollective(this.collective).find((page) => page.parentId === 0)
		},

		topLevelPages() {
			return this.rootPage
				? this.visibleSubpagesForCollective(this.collective, this.rootPage.id)
				: []
		},

		isRootPageSelected() {
			return !!this.rootPage && this.selectedPageIds.has(this.rootPage.id)
		},

		isTitleValid() {
			const length = this.title.trim().length
			return length > 0 && length <= TITLE_MAX_LENGTH
		},

		isSlugInUse() {
			return this.staticSites.some(({ slug }) => slug === this.slug)
		},

		isSlugValid() {
			// The slug of an existing website can't be changed
			return !!this.selectedStaticSite || (isValidSlug(this.slug) && !this.isSlugInUse)
		},

		slugHelperText() {
			if (this.selectedStaticSite) {
				return t('collectives', 'The web address can\'t be changed after publishing')
			}
			if (this.isSlugInUse) {
				return t('collectives', 'This web address is already used by another website of this collective')
			}
			return t('collectives', 'Lowercase letters, numbers and single hyphens')
		},
	},

	async created() {
		// The cached page list may be outdated or not loaded yet
		await Promise.all([
			this.getPagesForCollective(this.collective, false)
				.catch(displayError('Could not fetch collective pages')),
			this.getStaticSites(this.collective.id)
				.catch(displayError('Could not load static sites for collective')),
		])

		const [mostRecentStaticSite] = this.staticSites
		if (mostRecentStaticSite) {
			this.selectStaticSite(mostRecentStaticSite)
		} else {
			this.selectNewStaticSite()
		}
		this.loadingPages = false
	},

	methods: {
		t,
		staticSiteErrorMessage,

		formatTimestamp(timestamp) {
			return moment.unix(timestamp).format('LLL')
		},

		...mapActions(usePagesStore, ['getPagesForCollective']),

		...mapActions(useStaticSitesStore, ['getStaticSites', 'watchPublication']),

		onClose() {
			this.$emit('close')
		},

		onEscape(event) {
			// NcModal ignores Escape on all inputs, including checkboxes
			if (event.target.type === 'checkbox') {
				event.stopPropagation()
				this.onClose()
			}
		},

		/**
		 * @param {object} staticSite static site as returned by the backend
		 * @return {string} translated publication status
		 */
		staticSiteStatusLabel(staticSite) {
			if (this.isStaticSiteInProgress(staticSite)) {
				return t('collectives', 'Publishing')
			}
			switch (staticSite.status) {
				case 'published':
					return t('collectives', 'Published')
				case 'failed':
					return t('collectives', 'Publishing failed')
				default:
					return t('collectives', 'Publishing aborted')
			}
		},

		/**
		 * Prefill the form with the settings of an existing website to update it
		 *
		 * @param {object} staticSite static site to update
		 */
		selectStaticSite(staticSite) {
			this.selectedStaticSiteId = staticSite.id
			this.title = staticSite.title
			this.slug = staticSite.slug
			// Pages may have been deleted since the last publication
			const pageIds = new Set(this.allPageIds)
			this.selectedPageIds = new Set(staticSite.selectedPageIds.filter((id) => pageIds.has(id)))
		},

		/**
		 * Prefill the form for a new website of the whole collective (all pages)
		 */
		selectNewStaticSite() {
			this.selectedStaticSiteId = null
			this.title = this.collective.name
			this.slug = generateSlug(this.collective.name)
			this.selectedPageIds = new Set(this.allPageIds)
		},

		onToggleSelect(pageId) {
			// Toggling a page also selects/deselects all of its subpages
			const select = !this.selectedPageIds.has(pageId)
			const affectedIds = [
				pageId,
				...this.pagesTreeWalkForCollective(this.collective, pageId).map((page) => page.id),
			]

			const selectedPageIds = new Set(this.selectedPageIds)
			for (const id of affectedIds) {
				if (select) {
					selectedPageIds.add(id)
				} else {
					selectedPageIds.delete(id)
				}
			}
			this.selectedPageIds = selectedPageIds
		},

		onToggleExpand(pageId) {
			const expandedPageIds = new Set(this.expandedPageIds)
			if (expandedPageIds.has(pageId)) {
				expandedPageIds.delete(pageId)
			} else {
				expandedPageIds.add(pageId)
			}
			this.expandedPageIds = expandedPageIds
		},

		onPublishAsWebsite() {
			if (this.publishing) {
				return
			}

			const pageIds = Array.from(this.selectedPageIds)
			if (pageIds.length === 0) {
				showError(t('collectives', 'Please select at least one page to publish'))
				return
			}

			this.publishing = true
			const title = this.title.trim()
			const request = this.selectedStaticSite
				? updateStaticSite(this.collective.id, this.selectedStaticSite.id, pageIds, title)
				: createStaticSite(this.collective.id, pageIds, title, this.slug)
			request
				.then(({ data }) => {
					showSuccess(t('collectives', 'Website publishing started'))
					// Wait for the published URL in the background, the modal isn't needed for that
					this.watchPublication(this.collective.id, data.ocs.data)
					this.onClose()
				})
				.catch(displayError('Could not publish collective as website'))
				.finally(() => {
					this.publishing = false
				})
		},
	},
}
</script>

<style lang="scss" scoped>
.collective-publish-modal {
	:deep(.modal-wrapper .modal-container) {
		display: flex !important;
		padding-block: 4px 0;
		padding-inline: 12px;
	}

	:deep(.modal-wrapper .modal-container__content) {
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
}

.modal-publish {
	display: flex;
	flex-direction: column;
	height: 550px;
	max-height: 80vh;

	&__name {
		font-size: 21px;
		text-align: center;
	}

	&__sites {
		flex: 0 0 auto;
		padding-block-end: 8px;
	}

	&__sites-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;

		h3 {
			margin: 0;
			font-size: 16px;
		}
	}

	&__site-list {
		display: flex;
		flex-direction: column;
		gap: 4px;
		max-height: 160px;
		margin: 0;
		padding: 4px 0 0;
		overflow-y: auto;
	}

	&__site {
		list-style: none;
		padding: 4px 8px;
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius-large);
		word-break: break-all;

		&--selected {
			border-color: var(--color-primary-element);
		}
	}

	&__site-error {
		color: var(--color-error-text);
	}

	&__site-published-at {
		color: var(--color-text-maxcontrast);
	}

	&__site-select {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		width: 100%;
		min-height: auto;
		margin: 0;
		padding: 0;
		border: none;
		background: none;
		color: var(--color-main-text);
		font-weight: normal;
		text-align: start;
		cursor: pointer;

		span {
			color: var(--color-text-maxcontrast);
		}
	}

	&__fields {
		display: flex;
		flex-direction: column;
		gap: 8px;
		padding-block-end: 8px;
	}

	&__loading {
		flex: 1 1 auto;
	}

	&__tree {
		flex: 1 1 auto;
		margin: 0;
		padding: 0 0 8px;
		overflow-y: auto;
	}

	&__footer {
		display: flex;
		justify-content: center;
		flex: 0 0 auto;
		padding-block: 8px 12px;
	}

	&__collective-row {
		list-style: none;
	}
}
</style>
