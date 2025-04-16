<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="t('collectives', 'Templates')" size="normal" @closing="onClose">
		<!-- Template list -->
		<ul>
			<li v-for="(template, index) in templates"
				:key="`template-page-${index}`"
				class="template-list-item">
				<div class="template-list-item-icon" @click.prevent.stop>
					<NcEmojiPicker :show-preview="true"
						:allow-unselect="true"
						:selected-emoji="template.emoji"
						@select="onSelectEmoji($event, template.id)"
						@unselect="onUnselectEmoji(template.id)">
						<NcButton type="secondary"
							:aria-label="t('collectives', 'Select emoji for template')"
							:title="t('collectives', 'Select emoji')"
							class="button-template-emoji"
							@click.prevent>
							<template #icon>
								<NcLoadingIcon v-if="isLoading(template.id)"
									:size="20"
									fill-color="var(--color-text-maxcontrast)" />
								<div v-else-if="template.emoji">
									{{ template.emoji }}
								</div>
								<PageTemplateIcon v-else
									:size="20"
									fill-color="var(--color-text-maxcontrast)" />
							</template>
						</NcButton>
					</NcEmojiPicker>
				</div>
				<div class="template-list-item-title">
					<form v-if="renameId === template.id"
						class="template-list-item-title-text"
						@submit.prevent.stop="onRename"
						@click.prevent.stop>
						<NcTextField ref="renameField"
							:autofocus="true"
							:minlength="1"
							:required="true"
							:value.sync="renameName"
							@keyup.enter.prevent.stop
							@keyup.esc.prevent.stop="onStopRename" />
					</form>
					<a v-else
						class="template-list-item-title-text" href="#"
						@click="onOpen(template)">
						{{ template.title }}
					</a>
				</div>
				<div class="template-action-buttons" @click.prevent.stop>
					<NcActions :force-menu="true">
						<NcActionButton :close-after-click="true"
							@click="onInitRename(template)">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('collectives', 'Rename') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true"
							@click="onDelete(template.id)">
							<template #icon>
								<DeleteIcon :size="20" />
							</template>
							{{ deleteString(template.id) }}
						</NcActionButton>
					</NcActions>
					<NcActions>
						<NcActionButton class="action-button-add" @click="newPage(templateId)">
							<template #icon>
								<PlusIcon :size="20" fill-color="var(--color-main-text)" />
							</template>
							{{ t('collectives', 'Add a subpage') }}
						</NcActionButton>
					</NcActions>
				</div>
			</li>
		</ul>

		<!-- Template actions -->
		<template #actions>
			<NcButton type="secondary"
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
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useTemplatesStore } from '../../stores/templates.js'
import { showError } from '@nextcloud/dialogs'

import { NcActionButton, NcActions, NcButton, NcDialog, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'

import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'TemplatesDialog',

	components: {
		DeleteIcon,
		NcActionButton,
		NcActions,
		NcButton,
		NcDialog,
		NcEmojiPicker,
		NcLoadingIcon,
		NcTextField,
		PageTemplateIcon,
		PlusIcon,
		PencilIcon,
	},

	data() {
		return {
			renameId: null,
			renameName: '',
		}
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useTemplatesStore, [
			'hasSubpages',
			'templateFilePath',
			'templates',
		]),

		isLoading() {
			return (templateId) => {
				return this.loading(`templateRename-${templateId}`) || this.loading(`templateEmoji-${templateId}`)
			}
		},

		deleteString() {
			return (templateId) => {
				return this.hasSubpages(templateId)
					? t('collectives', 'Delete template and subpages')
					: t('collectives', 'Delete template')
			}
		},

		isCreating() {
			return this.loading('newTemplate')
		},
	},

	methods: {
		...mapActions(useCollectivesStore, [
			'setTemplatesCollectiveId',
		]),
		...mapActions(useTemplatesStore, [
			'createTemplate',
			'deleteTemplate',
			'renameTemplate',
			'setTemplateEmoji',
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

		async onDelete(templateId) {
			try {
				await this.deleteTemplate({ templateId })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not delete template'))
			}
		},

		async onSelectEmoji(emoji, templateId) {
			try {
				await this.setTemplateEmoji({ templateId, emoji })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not save emoji for template'))
			}
		},

		async onUnselectEmoji(templateId) {
			await this.onSelectEmoji('', templateId)
		},

		onInitRename(template) {
			this.renameName = template.title
			this.renameId = template.id
			this.$nextTick(() => {
				this.$refs.renameField[0].focus()
			})
		},

		async onRename() {
			try {
				await this.renameTemplate(this.renameId, this.renameName)
				this.onStopRename()
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename template'))
			}
		},

		onStopRename() {
			this.renameName = null
			this.renameId = null
		},

		async onCreate(parentId) {
			try {
				const templateId = await this.createTemplate(parentId)
				const newTemplate = this.templates.find((template) => template.id === templateId)
				this.onOpen(newTemplate)

			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create template'))
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

.template-list-item {
	display: flex;
	gap: calc(2 * var(--default-grid-baseline));

	height: var(--default-clickable-area);
	border-radius: var(--border-radius-element, var(--border-radius-large));
	margin: var(--default-grid-baseline) 0;

	&:not(:last-child) {
		border-bottom: 1px solid var(--color-border);
	}

	&:hover, &:focus, &:active {
		background-color: var(--color-background-hover);
	}

	&-icon {
		display: flex;
		justify-content: center;
		align-items: center;
		width: var(--default-clickable-area);
	}

	&-title {
		display: flex;
		flex-grow: 1;
		align-items: center;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;

		&-text {
			flex-grow: 1;
		}
	}
}

.template-action-buttons {
	display: flex;
	align-items: center;
	padding-inline: 12px;
}
</style>
flex-grow: 1;
