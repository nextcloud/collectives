<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('collectives', 'Templates')"
		data-cy-collectives="templates-dialog"
		class="templates-dialog"
		size="normal"
		@closing="onClose">
		<SkeletonLoading v-if="loading(`template-list-${templatesCollectiveId}`)" type="items" />
		<!-- Template list -->
		<ul v-else>
			<TemplateListItem
				v-for="(template, index) in rootTemplates"
				:key="`template-page-${index}`"
				:template="template"
				@delete="onDelete(template.id)"
				@open="onOpen(template)" />
		</ul>

		<!-- Template actions -->
		<template #actions>
			<NcButton
				variant="secondary"
				:aria-label="t('collectives', 'Add a template')"
				@click="onCreate(0)">
				<template #icon>
					<NcLoadingIcon v-if="isCreating" :size="20" />
				</template>
				{{ t('collectives', 'Add a template') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { NcButton, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import SkeletonLoading from '../SkeletonLoading.vue'
import TemplateListItem from './TemplateListItem.vue'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'
import { useTemplatesStore } from '../../stores/templates.js'
import displayError from '../../util/displayError.js'

export default {
	name: 'TemplatesDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		SkeletonLoading,
		TemplateListItem,
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useCollectivesStore, [
			'templatesCollectiveId',
		]),

		...mapState(useTemplatesStore, [
			'rootTemplates',
			'templatesLoaded',
			'templateFilePath',
		]),

		isCreating() {
			return this.loading('newTemplate')
		},
	},

	async mounted() {
		if (!this.templatesLoaded && !this.loading(`template-list-${this.templatesCollectiveId}`)) {
			await this.getTemplates()
				.catch(displayError('Could not fetch templates'))
		}
	},

	methods: {
		...mapActions(useCollectivesStore, [
			'setTemplatesCollectiveId',
		]),

		...mapActions(useTemplatesStore, [
			'createTemplate',
			'deleteTemplate',
			'getTemplates',
		]),

		onClose() {
			this.setTemplatesCollectiveId(undefined)
		},

		onOpen(template) {
			OCA.Viewer.open({
				path: `/${this.templateFilePath(template)}`,
				list: [],
			})
		},

		async onCreate(parentId) {
			try {
				const templateId = await this.createTemplate(parentId)
				const newTemplate = this.rootTemplates.find((template) => template.id === templateId)
				this.onOpen(newTemplate)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create template'))
			}
		},

		async onDelete(templateId) {
			try {
				await this.deleteTemplate({ templateId })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not delete template'))
			}
		},
	},
}
</script>

<style scoped lang="scss">
:deep(.modal-container) {
	height: calc(100vw - 120px) !important;
	max-height: 500px !important;
}

.template-action-buttons {
	display: flex;
	align-items: center;
	padding-inline: 12px;
}
.templates-dialog {
	// Make viewer modal overlay the templates dialog modal
	z-index: 9997;
}
</style>
