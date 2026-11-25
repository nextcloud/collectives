<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		size="normal"
		class="collective-publish-modal"
		:show="true"
		@update:show="onClose">
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
					@click="confirmDiscard(showWebsiteList)">
					<template #icon>
						<ArrowLeftIcon :size="20" />
					</template>
				</NcButton>
				<div>
					<h2 class="modal-publish__name">
						{{ headerTitle }}
					</h2>
					<p class="modal-publish__description">
						{{ headerDescription }}
					</p>
				</div>
			</div>
			<NcLoadingIcon v-if="loading" class="modal-publish__loading" :size="32" />
			<PublishWebsiteList
				v-else-if="view === 'list'"
				:staticSites="staticSites"
				:pages="pagesForCollective(collective)"
				@create="createWebsite"
				@delete="staticSiteToDelete = $event"
				@edit="editWebsite" />
			<template v-else>
				<PublishStepIndicator
					class="modal-publish__steps"
					:steps="[t('collectives', 'Page selection'), t('collectives', 'Settings')]"
					:current="step === 'pages' ? 0 : 1" />
				<PublishPageSelection
					v-if="step === 'pages'"
					v-model:selectedPageIds="selectedPageIds"
					:collective="collective" />
				<PublishSettingsForm
					v-else
					v-model:title="title"
					v-model:slug="slug"
					v-model:theme="theme"
					:slugReadOnly="isSlugReadOnly"
					:slugTaken="isSlugTaken" />
				<div class="modal-publish__footer">
					<span class="modal-publish__selection-count">
						{{ selectionCountLabel }}
					</span>
					<div class="modal-publish__footer-buttons">
						<template v-if="step === 'pages'">
							<NcButton @click="confirmDiscard(showWebsiteList)">
								{{ t('collectives', 'Back') }}
							</NcButton>
							<NcButton
								variant="primary"
								:disabled="selectedPageIds.size === 0"
								@click="step = 'settings'">
								{{ t('collectives', 'Continue') }}
							</NcButton>
						</template>
						<template v-else>
							<NcButton :disabled="publishing" @click="step = 'pages'">
								{{ t('collectives', 'Back') }}
							</NcButton>
							<NcButton
								variant="primary"
								:loading="publishing"
								:disabled="publishing || !isValidTitle(title) || !isSlugValid"
								@click="onPublishAsWebsite">
								{{ selectedStaticSite ? t('collectives', 'Update website') : t('collectives', 'Publish website') }}
							</NcButton>
						</template>
					</div>
				</div>
			</template>
			<PublishDeleteDialog
				v-if="staticSiteToDelete"
				:collective="collective"
				:staticSite="staticSiteToDelete"
				@close="staticSiteToDelete = null" />
			<NcDialog
				v-if="pendingAction"
				:name="t('collectives', 'Discard changes?')"
				:message="t('collectives', 'Your changes to this website will be lost.')"
				:buttons="discardButtons"
				@closing="onDiscardDialogClosing" />
		</div>
	</NcModal>
</template>

<script>
import { showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import PublishDeleteDialog from './PublishDeleteDialog.vue'
import PublishPageSelection from './PublishPageSelection.vue'
import PublishSettingsForm from './PublishSettingsForm.vue'
import PublishStepIndicator from './PublishStepIndicator.vue'
import PublishWebsiteList from './PublishWebsiteList.vue'
import { createStaticSite, updateStaticSite } from '../../apis/collectives/index.js'
import { usePagesStore } from '../../stores/pages.js'
import { useStaticSitesStore } from '../../stores/staticSites.js'
import displayError from '../../util/displayError.js'
import { isValidSlug } from '../../util/staticSiteSlug.js'
import { THEME_WIKI } from '../../util/staticSiteTheme.js'
import { isValidTitle } from '../../util/staticSiteTitle.js'

export default {
	name: 'CollectivePublishModal',

	components: {
		ArrowLeftIcon,
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcModal,
		PublishDeleteDialog,
		PublishPageSelection,
		PublishSettingsForm,
		PublishStepIndicator,
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
			/** @type {'pages'|'settings'} */
			step: 'pages',
			/** @type {?string} form values when the form got opened, to detect changes */
			initialFormValues: null,
			/** @type {?(() => void)} action waiting for the user to confirm discarding changes */
			pendingAction: null,
			/** @type {?object} static site waiting for the user to confirm deleting it */
			staticSiteToDelete: null,
			selectedPageIds: new Set(),
			loading: true,
			publishing: false,
			/** @type {?number} null for a new website */
			selectedStaticSiteId: null,
			/** @type {string[]} slugs the backend reported as taken by websites of other collectives */
			takenSlugs: [],
			title: this.collective.name,
			slug: '',
			theme: THEME_WIKI,
		}
	},

	computed: {
		...mapState(usePagesStore, [
			'pageAncestorIdsForCollective',
			'pagesForCollective',
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

		headerDescription() {
			if (this.view === 'list') {
				return t('collectives', 'Publish pages of this collective as static websites.')
			}
			return this.step === 'pages'
				? t('collectives', 'Choose which pages become part of the website.')
				: t('collectives', 'Title and address of the website.')
		},

		formValues() {
			return JSON.stringify([this.title, this.slug, this.theme, [...this.selectedPageIds].sort()])
		},

		hasUnsavedChanges() {
			return this.view === 'form' && this.formValues !== this.initialFormValues
		},

		discardButtons() {
			return [
				{
					label: t('collectives', 'Keep editing'),
					variant: 'secondary',
				},
				{
					label: t('collectives', 'Discard changes'),
					variant: 'error',
					callback: () => this.pendingAction(),
				},
			]
		},

		allPageIds() {
			return this.pagesTreeWalkForCollective(this.collective).map((page) => page.id)
		},

		rootPageId() {
			return this.pagesForCollective(this.collective).find((page) => page.parentId === 0)?.id
		},

		selectionCountLabel() {
			return n(
				'collectives',
				'{selected} of %n page selected',
				'{selected} of %n pages selected',
				this.allPageIds.length,
				{ selected: this.selectedPageIds.size },
			)
		},

		// Slugs are unique across all collectives, websites of other collectives are only known after a conflict
		isSlugTaken() {
			return this.staticSites.some(({ id, slug }) => id !== this.selectedStaticSiteId && slug === this.slug)
				|| this.takenSlugs.includes(this.slug)
		},

		// The slug is part of the public URL, so it can't be changed once the website got published
		isSlugReadOnly() {
			return !!this.selectedStaticSite?.publishedUrl
		},

		isSlugValid() {
			return this.isSlugReadOnly || (isValidSlug(this.slug) && !this.isSlugTaken)
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
		isValidTitle,

		...mapActions(usePagesStore, ['getPagesForCollective']),

		...mapActions(useStaticSitesStore, ['getStaticSites', 'watchPublication']),

		onClose() {
			this.confirmDiscard(() => this.$emit('close'))
		},

		/**
		 * Run an action that leaves the form, after the user confirmed to discard changes
		 *
		 * @param {() => void} action action to run
		 */
		confirmDiscard(action) {
			if (this.hasUnsavedChanges) {
				this.pendingAction = action
			} else {
				action()
			}
		},

		// The dialog reports being closed only after its closing animation, which is too late if
		// the user leaves the form again right away
		onDiscardDialogClosing() {
			this.pendingAction = null
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
			this.openForm()
		},

		editWebsite(staticSite) {
			this.selectStaticSite(staticSite)
			this.openForm()
		},

		openForm() {
			this.step = 'pages'
			this.initialFormValues = this.formValues
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
			this.theme = staticSite.theme
			// Pages may have been deleted or moved since the last publication:
			// drop deleted ones and add ancestor pages of the remaining ones
			const pageIds = new Set(this.allPageIds)
			const selectedPageIds = new Set(staticSite.selectedPageIds.filter((id) => pageIds.has(id)))
			// The home page is always part of the website
			if (this.rootPageId) {
				selectedPageIds.add(this.rootPageId)
			}
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
			this.theme = THEME_WIKI
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
			this.publishing = true
			const title = this.title.trim()
			const slug = this.slug
			const theme = this.theme
			const request = this.selectedStaticSite
				? updateStaticSite(this.collective.id, this.selectedStaticSite.id, pageIds, title, slug, theme)
				: createStaticSite(this.collective.id, pageIds, title, slug, theme)
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
					// Another publication of the collective started meanwhile, e.g. by another member (423),
					// or the publish service failed and the website got stored as failed to retry later (502, 503)
					if ([423, 502, 503].includes(e.response?.status)) {
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
		padding-inline: 0;
	}

	:deep(.modal-wrapper .modal-container__content) {
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
}

// Header, step bar and footer span the full width despite this padding
$modal-padding: 24px;

.modal-publish {
	display: flex;
	flex-direction: column;
	max-height: 80vh;
	padding-block-end: 25px;
	padding-inline: $modal-padding;

	// Fixed height, so the modal doesn't jump while pages get expanded
	&--form {
		height: 600px;
		padding-block-end: 0;
	}

	&__header {
		display: flex;
		align-items: center;
		gap: 8px;
		flex: 0 0 auto;
		margin-block-end: 12px;
		margin-inline: -$modal-padding;
		padding-block: 8px 12px;
		// Space for the close button of the modal
		padding-inline: $modal-padding calc($modal-padding + 44px);
		border-bottom: 1px solid var(--color-border-dark);
	}

	&__name {
		margin: 0;
		font-size: 21px;
	}

	&__description {
		color: var(--color-text-maxcontrast);
	}

	&__steps {
		flex: 0 0 auto;
		margin-block: -12px 8px;
		margin-inline: -$modal-padding;
		padding-inline: $modal-padding;
		border-bottom: 1px solid var(--color-border-dark);
		background-color: var(--color-background-dark);
	}

	&__loading {
		flex: 1 1 auto;
	}

	&__footer {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
		flex: 0 0 auto;
		margin-inline: -$modal-padding;
		padding-block: 12px;
		padding-inline: $modal-padding;
		border-top: 1px solid var(--color-border-dark);
	}

	&__selection-count {
		color: var(--color-text-maxcontrast);
	}

	&__footer-buttons {
		display: flex;
		gap: 8px;
		margin-inline-start: auto;
	}
}
</style>
