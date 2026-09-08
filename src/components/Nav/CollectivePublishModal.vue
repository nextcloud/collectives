<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		size="normal"
		class="collective-publish-modal"
		@close="onClose">
		<div class="modal-publish">
			<h2 class="modal-publish__name">
				{{ t('collectives', 'Publish website for collective {name}', { name: collective.name }) }}
			</h2>
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
				<NcButton variant="primary" @click="onPublishAsWebsite">
					{{ t('collectives', 'Publish as Website') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import PublishPageTreeItem from './PublishPageTreeItem.vue'
import PublishTreeRow from './PublishTreeRow.vue'
import { usePagesStore } from '../../stores/pages.js'

export default {
	name: 'CollectivePublishModal',

	components: {
		NcButton,
		NcModal,
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
		'publish',
	],

	data() {
		return {
			selectedPageIds: new Set(),
			expandedPageIds: new Set(),
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
			const pageIds = Array.from(this.selectedPageIds)
			// TODO: replace with a real backend call once a publish API is available
			console.info('Publish as website', { collectiveId: this.collective.id, pageIds })
			this.$emit('publish', { collectiveId: this.collective.id, pageIds })
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
		padding-block: 8px 4px;
	}

	&__collective-row {
		list-style: none;
	}
}
</style>
