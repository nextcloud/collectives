<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		size="normal"
		class="collective-publish-modal"
		@close="onClose">
		<div
			class="modal-publish"
			:class="{ 'modal-publish--form': view === 'form' }"
			@keydown.esc="onEscape">
			<div class="modal-publish__header">
				<NcButton
					v-if="view === 'form'"
					variant="tertiary"
					:aria-label="t('collectives', 'Back to websites')"
					:disabled="publishing"
					@click="showWebsiteList">
					<template #icon>
						<ArrowLeftIcon :size="20" />
					</template>
				</NcButton>
				<div>
					<h2 class="modal-publish__name">
						{{ headerTitle }}
					</h2>
					<p v-if="view === 'list'" class="modal-publish__description">
						{{ t('collectives', 'Publish pages of this collective as static websites.') }}
					</p>
				</div>
			</div>
			<NcLoadingIcon v-if="loading" class="modal-publish__loading" :size="32" />
			<PublishWebsiteList
				v-else-if="view === 'list'"
				:staticSites="staticSites"
				@create="createWebsite"
				@edit="editWebsite" />
			<template v-else>
				<div class="modal-publish__fields">
					<NcTextField
						v-model="title"
						:label="t('collectives', 'Website title')"
						:error="!isTitleValid"
						:maxlength="titleMaxLength"
						required />
					<!-- TRANSLATORS The web address is the part of the website URL that identifies the published collective -->
					<NcTextField
						v-model="slug"
						:label="t('collectives', 'Web address')"
						:error="slug !== '' && !isSlugValid"
						:disabled="!!selectedStaticSite"
						:helperText="slugHelperText"
						:maxlength="slugMaxLength"
						required />
				</div>
				<PublishPageSelection
					v-model:selectedPageIds="selectedPageIds"
					:collective="collective" />
				<div class="modal-publish__footer">
					<NcButton
						variant="primary"
						:loading="publishing"
						:disabled="publishing || !isTitleValid || !isSlugValid"
						@click="onPublishAsWebsite">
						{{ selectedStaticSite ? t('collectives', 'Update website') : t('collectives', 'Publish website') }}
					</NcButton>
				</div>
			</template>
		</div>
	</NcModal>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import PublishPageSelection from './PublishPageSelection.vue'
import PublishWebsiteList from './PublishWebsiteList.vue'
import { createStaticSite, updateStaticSite } from '../../apis/collectives/index.js'
import { usePagesStore } from '../../stores/pages.js'
import { useStaticSitesStore } from '../../stores/staticSites.js'
import displayError from '../../util/displayError.js'
import { isValidSlug, SLUG_MAX_LENGTH } from '../../util/staticSiteSlug.js'

const TITLE_MAX_LENGTH = 255

export default {
	name: 'CollectivePublishModal',

	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		ArrowLeftIcon,
		NcTextField,
		PublishPageSelection,
		PublishWebsiteList,
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
			/** @type {'list'|'form'} */
			view: 'list',
			selectedPageIds: new Set(),
			loading: true,
			publishing: false,
			/** @type {?number} null for a new website */
			selectedStaticSiteId: null,
			/** @type {string[]} slugs the backend reported as taken by websites of other collectives */
			takenSlugs: [],
			title: this.collective.name,
			slug: '',
			titleMaxLength: TITLE_MAX_LENGTH,
			slugMaxLength: SLUG_MAX_LENGTH,
		}
	},

	computed: {
		...mapState(usePagesStore, [
			'pageAncestorIdsForCollective',
			'pagesTreeWalkForCollective',
		]),

		...mapState(useStaticSitesStore, ['staticSitesForCollective']),

		staticSites() {
			return this.staticSitesForCollective(this.collective.id)
		},

		selectedStaticSite() {
			return this.staticSites.find(({ id }) => id === this.selectedStaticSiteId) ?? null
		},

		headerTitle() {
			if (this.view === 'list') {
				return t('collectives', 'Websites')
			}
			return this.selectedStaticSite
				? t('collectives', 'Update website')
				: t('collectives', 'New website')
		},

		allPageIds() {
			return this.pagesTreeWalkForCollective(this.collective).map((page) => page.id)
		},

		isTitleValid() {
			const length = this.title.trim().length
			return length > 0 && length <= TITLE_MAX_LENGTH
		},

		// Slugs are unique across all collectives, websites of other collectives are only known after a conflict
		isSlugTaken() {
			return this.staticSites.some(({ slug }) => slug === this.slug)
				|| this.takenSlugs.includes(this.slug)
		},

		isSlugValid() {
			// The slug of an existing website can't be changed
			return !!this.selectedStaticSite || (isValidSlug(this.slug) && !this.isSlugTaken)
		},

		slugHelperText() {
			if (this.selectedStaticSite) {
				return t('collectives', 'The web address can\'t be changed after publishing')
			}
			if (this.isSlugTaken) {
				return t('collectives', 'This web address is already taken by another website')
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
		this.loading = false
	},

	methods: {
		t,

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

		showWebsiteList() {
			this.view = 'list'
		},

		createWebsite() {
			this.selectNewStaticSite()
			this.view = 'form'
		},

		editWebsite(staticSite) {
			this.selectStaticSite(staticSite)
			this.view = 'form'
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
			// Pages may have been deleted or moved since the last publication:
			// drop deleted ones and add ancestor pages of the remaining ones
			const pageIds = new Set(this.allPageIds)
			const selectedPageIds = new Set(staticSite.selectedPageIds.filter((id) => pageIds.has(id)))
			for (const id of Array.from(selectedPageIds)) {
				for (const ancestorId of this.pageAncestorIdsForCollective(this.collective, id)) {
					selectedPageIds.add(ancestorId)
				}
			}
			this.selectedPageIds = selectedPageIds
		},

		/**
		 * Prefill the form for a new website of the whole collective (all pages)
		 */
		selectNewStaticSite() {
			this.selectedStaticSiteId = null
			this.title = this.collective.name
			this.slug = ''
			this.selectedPageIds = new Set(this.allPageIds)
		},

		reloadStaticSites() {
			return this.getStaticSites(this.collective.id)
				.catch(displayError('Could not load static sites for collective'))
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
			const slug = this.slug
			const request = this.selectedStaticSite
				? updateStaticSite(this.collective.id, this.selectedStaticSite.id, pageIds, title)
				: createStaticSite(this.collective.id, pageIds, title, slug)
			request
				.then(({ data }) => {
					showSuccess(t('collectives', 'Website publishing started'))
					// Wait for the result in the background, also once the modal got closed
					this.watchPublication(this.collective, data.ocs.data)
					this.showWebsiteList()
					return this.reloadStaticSites()
				})
				.catch((e) => {
					if (e.response?.status === 409) {
						// Shown at the web address field
						this.takenSlugs = [...this.takenSlugs, slug]
						return
					}
					displayError('Could not publish collective as website')(e)
					if (e.response?.status === 423) {
						// Another publication of the collective started meanwhile, e.g. by another member
						this.showWebsiteList()
						return this.reloadStaticSites()
					}
				})
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
		// Never wider than the full screen modal on small screens
		width: min(750px, 100%);
		padding-block: 4px 0;
		padding-inline: 24px;
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
	max-height: 80vh;
	padding-block-end: 25px;

	// Fixed height, so the modal doesn't jump while pages get expanded
	&--form {
		height: 480px;
		padding-block-end: 0;
	}

	&__header {
		display: flex;
		align-items: center;
		gap: 8px;
		flex: 0 0 auto;
		margin-block-end: 12px;
		// Space for the close button of the modal
		padding-block: 8px 12px;
		padding-inline-end: 44px;
		border-bottom: 1px solid var(--color-border-dark);
	}

	&__name {
		margin: 0;
		font-size: 21px;
	}

	&__description {
		color: var(--color-text-maxcontrast);
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

	&__footer {
		display: flex;
		justify-content: center;
		flex: 0 0 auto;
		padding-block: 8px 12px;
	}
}
</style>
