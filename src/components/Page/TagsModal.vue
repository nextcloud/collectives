<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="t('collectives', 'Manage tags')"
		class="tags-modal"
		@closing="onClose">
		<!-- Search or create input -->
		<div class="tags-modal__input">
			<NcTextField :value.sync="input"
				:label="t('collectives', 'Search or create tag')">
				<TagIcon :size="20" />
			</NcTextField>
		</div>

		<!-- Tags list -->
		<ul class="tags-modal__tags">
			<li v-for="tag in filteredTags"
				:key="tag.id"
				:style="tagListStyle(tag)"
				class="tags-modal__tag">
				<form v-if="renameTag === tag.id"
					class="tags-modal__name-form"
					@submit.prevent.stop="onRename(tag)"
					@click.prevent.stop>
					<NcTextField ref="renameField"
						:placeholder="t('collectives', 'Tag name')"
						:label-outside="true"
						:autofocus="true"
						:minlength="1"
						:required="true"
						:value.sync="renameName"
						trailing-button-icon="close"
						:show-trailing-button="true"
						@keyup.enter.prevent.stop
						@keyup.esc.prevent.stop="onStopRename"
						@trailing-button-click="onStopRename" />
				</form>
				<NcCheckboxRadioSwitch v-else
					:checked="isChecked(tag)"
					:label="tag.name"
					:loading="loading(`page-tag-${pageId}-${tag.id}`)"
					class="tags-modal__tag-checkbox"
					@update:checked="onCheckUpdate(tag, $event)">
					{{ tag.name }}
				</NcCheckboxRadioSwitch>

				<!-- Color picker -->
				<NcColorPicker :value="`#${tag.color}`"
					:shown="openedPicker === tag.id"
					class="tags-modal__tag-color"
					@update:shown="openedPicker = $event ? tag.id : false"
					@submit="onSubmitColor(tag, $event)">
					<NcButton :aria-label="t('collectives', 'Change tag color')" type="tertiary">
						<template #icon>
							<CircleIcon v-if="tag.color" :size="24" fill-color="var(--color-circle-icon)" />
							<CircleOutlineIcon v-else :size="24" fill-color="var(--color-circle-icon" />
						</template>
					</NcButton>
				</NcColorPicker>

				<!-- Actions menu -->
				<NcActions :force-menu="true">
					<NcActionButton :close-after-click="true"
						@click="onInitRename(tag)">
						<template #icon>
							<PencilIcon :size="20" />
						</template>
						{{ t('collectives', 'Rename') }}
					</NcActionButton>
					<NcPopover popup-role="dialog">
						<template #trigger="{ attrs }">
							<NcActionButton v-bind="attrs">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
								{{ t('collectives', 'Delete') }}
							</NcActionButton>
						</template>
						<div class="tags-modal__delete-dialog" role="dialog" aria-modal="true">
							{{ t('collectives', 'Tag {name} gets deleted for all pages.', { name: tag.name }) }}
							<NcButton variant="error" @click="onDelete(tag)">
								{{ t('collectives', 'Delete') }}
							</NcButton>
						</div>
					</NcPopover>
				</NcActions>
			</li>

			<li>
				<NcButton v-if="showCreateTag"
					alignment="start"
					class="tags-modal__tag-create"
					native-type="submit"
					type="tertiary"
					@click="onNewTag">
					{{ input.trim() }}<br>
					<span class="tags-modal__tag-create-subline">{{ t('collectives', 'Create new tag') }}</span>
					<template #icon>
						<PlusIcon />
					</template>
				</NcButton>
			</li>
		</ul>
	</NcDialog>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { useTagsStore } from '../../stores/tags.js'
import { usePagesStore } from '../../stores/pages.js'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { NcActions, NcActionButton, NcButton, NcCheckboxRadioSwitch, NcColorPicker, NcDialog, NcPopover, NcTextField } from '@nextcloud/vue'
import CircleIcon from 'vue-material-design-icons/Circle.vue'
import CircleOutlineIcon from 'vue-material-design-icons/CircleOutline.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'

export default {
	name: 'TagsModal',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcCheckboxRadioSwitch,
		NcColorPicker,
		NcDialog,
		NcPopover,
		NcTextField,
		CircleIcon,
		CircleOutlineIcon,
		DeleteIcon,
		PencilIcon,
		PlusIcon,
		TagIcon,
	},

	props: {
		pageId: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			input: '',
			openedPicker: false,
			renameTag: false,
			renameName: '',
		}
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(usePagesStore, ['pages']),
		...mapState(useTagsStore, ['sortedTags']),

		page() {
			return this.pages.find(p => p.id === this.pageId)
		},

		filteredTags() {
			if (this.input.trim() === '') {
				return this.sortedTags
			}

			return this.sortedTags
				.filter(tag => tag.name.normalize().toLowerCase().includes(this.input.normalize().toLowerCase()))
		},

		showCreateTag() {
			return this.input.trim() !== ''
				&& !this.sortedTags.some(t => t.name.trim().toLocaleLowerCase() === this.input.trim().toLocaleLowerCase())
		},
	},

	methods: {
		...mapActions(useRootStore, ['done', 'load']),
		...mapActions(usePagesStore, ['addPageTag', 'removePageTag']),
		...mapActions(useTagsStore, ['createTag', 'deleteTag', 'updateTag']),

		tagListStyle(tag) {
			// No color, no style
			if (!tag.color) {
				return {
					'--color-circle-icon': 'var(--color-text-maxcontrast)',
				}
			}
			return {
				'--color-circle-icon': `#${tag.color}`,
			}
		},

		onClose() {
			this.$emit('close')
		},

		isChecked(tag) {
			return (this.page.tags.includes(tag.id))
		},

		async onCheckUpdate(tag, checked) {
			if (checked) {
				try {
					await this.addPageTag({ pageId: this.pageId }, tag.id)
					showSuccess(t('collectives', 'Added tag {name} to page', { name: tag.name }))
				} catch (e) {
					showError(t('collectives', 'Could not add tag {name} to page', { name: tag.name }))
					throw e
				}
			} else {
				try {
					await this.removePageTag({ pageId: this.pageId }, tag.id)
					showSuccess(t('collectives', 'Removed tag {name} from page', { name: tag.name }))
				} catch (e) {
					showError(t('collectives', 'Could not remove tag {name} from page', { name: tag.name }))
					throw e
				}
			}
		},

		async onSubmitColor(tag, color) {
			tag.color = color.replace('#', '')
			this.load(`page-tag-${this.pageId}-${tag.id}`)
			try {
				await this.updateTag(tag)
				this.openedPicker = false
				showSuccess(t('collectives', 'Updated tag {name}', { name: tag.name }))
			} catch (e) {
				showError(t('collectives', 'Could not update tag {name}', { name: tag.name }))
				throw e
			} finally {
				this.done(`page-tag-${this.pageId}-${tag.id}`)
			}
		},

		onInitRename(tag) {
			this.renameName = tag.name
			this.renameTag = tag.id
			this.$nextTick(() => {
				this.$refs.renameField[0].focus()
			})
		},

		async onRename(tag) {
			tag.name = this.renameName
			this.load(`page-tag-${this.pageId}-${tag.id}`)
			try {
				await this.updateTag(tag)
				this.onStopRename()
				showSuccess(t('collectives', 'Updated tag {name}', { name: tag.name }))
			} catch (e) {
				showError(t('collectives', 'Could not update tag {name}', { name: tag.name }))
				throw e
			} finally {
				this.done(`page-tag-${this.pageId}-${tag.id}`)
			}
		},

		onStopRename() {
			this.renameTag = false
			this.renameName = ''
		},

		async onDelete(tag) {
			this.load(`page-tag-${this.pageId}-${tag.id}`)
			try {
				await this.deleteTag(tag)
				showSuccess(t('collectives', 'Deleted tag {name}', { name: tag.name }))
			} catch (e) {
				showError(t('collectives', 'Could not delete tag {name}', { name: tag.name }))
				throw e
			} finally {
				this.done(`page-tag-${this.pageId}-${tag.id}`)
			}
		},

		async onNewTag() {
			const name = this.input.trim()
			try {
				await this.createTag({ name, color: '' })
				showSuccess(t('collectives', 'Created tag {name}', { name }))
			} catch (e) {
				showError(t('collectives', 'Could not create tag {name}', { name }))
				throw e
			}

			const tag = this.sortedTags.find(t => t.name === name)
			this.load(`page-tag-${this.pageId}-${tag.id}`)
			try {
				await this.addPageTag({ pageId: this.pageId }, tag.id)
				this.input = ''
				showSuccess(t('collectives', 'Added tag {name} to page', { name }))
			} catch (e) {
				showError(t('collectives', 'Could not add tag {name} to page', { name }))
				throw e
			} finally {
				this.done(`page-tag-${this.pageId}-${tag.id}`)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.tags-modal {
	display: flex;
	flex-direction: column;
}

.tags-modal__input {
	display: flex;
	position: sticky;
	top: 0;
	z-index: 9;
	background-color: var(--color-main-background);
	gap: 8px;
	padding-block-end: 8px;
	align-items: flex-end;
}

.tags-modal__tags {
	padding-block: 8px;
	gap: var(--default-grid-baseline);
	display: flex;
	flex-direction: column;

	li {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;

		// Make switch full width
		:deep(.checkbox-radio-switch) {
			width: 100%;

			.checkbox-content {
				max-width: none;
				box-sizing: border-box;;
				min-height: calc(var(--default-grid-baseline) * 2 + var(--default-clickable-area));
			}
		}

		.tags-modal__name-form {
			// Align with NcCheckboxRadioSwitch
			margin-block: 4px;
			padding-left: 23px;
			flex-grow: 1;
		}
	}

	.tags-modal__tag-create {
		width: 100%;

		:deep(span) {
			text-align: start;
		}
		&-subline {
			font-weight: normal;
		}
	}
}

.tags-modal__delete-dialog {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	padding: calc(var(--default-grid-baseline) * 2);
}
</style>
