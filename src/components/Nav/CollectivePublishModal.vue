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
			<div class="modal-publish__fields">
				<NcTextField
					v-model="title"
					:label="t('collectives', 'Website title')"
					:error="!isTitleValid"
					:maxlength="titleMaxLength" />
				<!-- TRANSLATORS The web address is the part of the website URL that identifies the published collective -->
				<NcTextField
					v-model="slug"
					:label="t('collectives', 'Web address')"
					:error="!isSlugValid"
					:helperText="t('collectives', 'Lowercase letters, numbers and single hyphens')"
					:maxlength="slugMaxLength" />
			</div>
			<ul class="modal-publish__tree">
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
					:disabled="publishing || !isTitleValid || !isSlugValid"
					@click="onPublishAsWebsite">
					{{ t('collectives', 'Publish website') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import PublishPageTreeItem from './PublishPageTreeItem.vue'
import PublishTreeRow from './PublishTreeRow.vue'
import { createStaticSite } from '../../apis/collectives/index.js'
import { usePagesStore } from '../../stores/pages.js'
import displayError from '../../util/displayError.js'
import { generateSlug, isValidSlug, SLUG_MAX_LENGTH } from '../../util/staticSiteSlug.js'

const TITLE_MAX_LENGTH = 255

export default {
	name: 'CollectivePublishModal',

	components: {
		NcButton,
		NcModal,
		NcTextField,
		PageTemplateIcon,
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
			publishing: false,
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

		isSlugValid() {
			return isValidSlug(this.slug)
		},

	},

	created() {
		// Preselect the whole collective (all pages) when the modal is opened
		this.selectedPageIds = new Set(this.allPageIds)
	},

	methods: {
		t,

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
			createStaticSite(this.collective.id, pageIds, this.title.trim(), this.slug)
				.then(() => {
					showSuccess(t('collectives', 'Website publishing started'))
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

	&__fields {
		display: flex;
		flex-direction: column;
		gap: 8px;
		padding-block-end: 8px;
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
