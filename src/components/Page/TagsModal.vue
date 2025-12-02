<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('collectives', 'Manage tags')"
		class="tags-modal"
		@closing="onClose">
		<!-- Search or create input -->
		<div class="tags-modal__input">
			<NcTextField
				v-model="input"
				:label="t('collectives', 'Search or create tag')">
				<TagIcon :size="20" />
			</NcTextField>
		</div>

		<!-- Tags list -->
		<ul class="tags-modal__tags">
			<li
				v-for="tag in filteredTags"
				:key="tag.id"
				:style="tagListStyle(tag)"
				class="tags-modal__tag">
				<form
					v-if="renameTag === tag.id"
					class="tags-modal__name-form"
					@submit.prevent.stop="onRename(tag)"
					@click.prevent.stop>
					<NcTextField
						ref="renameField"
						v-model="renameName"
						:placeholder="t('collectives', 'Tag name')"
						:label-outside="true"
						:autofocus="true"
						:minlength="1"
						:required="true"
						trailing-button-icon="close"
						:show-trailing-button="true"
						@keyup.enter.prevent.stop
						@keyup.esc.prevent.stop="onStopRename"
						@trailing-button-click="onStopRename" />
				</form>
				<NcCheckboxRadioSwitch
					v-else
					:model-value="isChecked(tag)"
					:label="tag.name"
					:loading="loading(`page-tag-${pageId}-${tag.id}`)"
					:disabled="tag.deleted"
					class="tags-modal__tag-checkbox"
					@update:modelValue="onCheckUpdate(tag, $event)">
					<template v-if="!tag.deleted">
						{{ tag.name }}
					</template>
					<template v-else>
						<span class="tags-modal__tag-title deleted">{{ tag.name }}</span>
						<span v-if="countTagPages(tag.id) > 0">{{ n('collectives', '(removed from %n page)', '(removed from %n pages)', countTagPages(tag.id)) }}</span>
					</template>
				</NcCheckboxRadioSwitch>

				<!-- Color picker -->
				<NcColorPicker
					v-model="tag.color"
					:shown="openedPicker === tag.id"
					class="tags-modal__tag-color"
					clearable
					@update:shown="openedPicker = $event ? tag.id : false"
					@submit="onSubmitColor(tag)">
					<NcButton :aria-label="t('collectives', 'Change tag color')" variant="tertiary">
						<template #icon>
							<CircleIcon v-if="tag.color" :size="24" fill-color="var(--color-circle-icon)" />
							<CircleOutlineIcon v-else :size="24" fill-color="var(--color-circle-icon" />
						</template>
					</NcButton>
				</NcColorPicker>

				<!-- Actions menu -->
				<NcActions :force-menu="true">
					<NcActionButton
						:close-after-click="true"
						@click="onInitRename(tag)">
						<template #icon>
							<PencilIcon :size="20" />
						</template>
						{{ t('collectives', 'Rename') }}
					</NcActionButton>
					<NcActionButton
						v-if="!tag.deleted"
						:close-after-click="true"
						@click="onMarkDeleted(tag)">
						<template #icon>
							<DeleteIcon :size="20" />
						</template>
						{{ t('collectives', 'Delete') }}
					</NcActionButton>
					<NcActionButton
						v-else
						:close-after-click="true"
						@click="onRestore(tag)">
						<template #icon>
							<RestoreIcon :size="20" />
						</template>
						{{ t('collectives', 'Restore') }}
					</NcActionButton>
				</NcActions>
			</li>

			<li>
				<NcButton
					v-if="showCreateTag"
					:aria-label="t('collectives', 'Create new tag {tag}', { tag: input.trim() })"
					alignment="start"
					class="tags-modal__tag-create"
					type="submit"
					variant="tertiary"
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcActionButton, NcActions, NcButton, NcCheckboxRadioSwitch, NcColorPicker, NcDialog, NcTextField } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import CircleIcon from 'vue-material-design-icons/Circle.vue'
import CircleOutlineIcon from 'vue-material-design-icons/CircleOutline.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import TagIcon from 'vue-material-design-icons/TagOutline.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { useTagsStore } from '../../stores/tags.js'

export default {
	name: 'TagsModal',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcCheckboxRadioSwitch,
		NcColorPicker,
		NcDialog,
		NcTextField,
		CircleIcon,
		CircleOutlineIcon,
		DeleteIcon,
		PencilIcon,
		PlusIcon,
		RestoreIcon,
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
			deletedTagIds: [],
		}
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(usePagesStore, ['pages']),
		...mapState(useTagsStore, ['sortedTags']),

		page() {
			return this.pages.find((p) => p.id === this.pageId)
		},

		decoratedTags() {
			return this.sortedTags
				.map((tag) => ({ ...tag, deleted: this.deletedTagIds.includes(tag.id) }))
		},

		filteredTags() {
			if (this.input.trim() === '') {
				return this.decoratedTags
			}

			return this.decoratedTags
				.filter((tag) => tag.name.normalize().toLowerCase().includes(this.input.normalize().toLowerCase()))
		},

		countTagPages() {
			return (tagId) => this.pages.filter((p) => p.tags.includes(tagId)).length
		},

		showCreateTag() {
			return this.input.trim() !== ''
				&& !this.sortedTags.some((t) => t.name.trim().toLocaleLowerCase() === this.input.trim().toLocaleLowerCase())
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
			this.deleteMarked()
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

		async onSubmitColor(tag) {
			tag.color = tag.color?.replace('#', '') || ''
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

		onMarkDeleted(tag) {
			this.deletedTagIds.push(tag.id)
		},

		onRestore(tag) {
			const idx = this.deletedTagIds.indexOf(tag.id)
			if (idx > -1) {
				this.deletedTagIds.splice(idx, 1)
			}
		},

		async deleteMarked() {
			if (this.deletedTagIds.length < 1) {
				return
			}
			for (const tagId of this.deletedTagIds) {
				const tag = this.sortedTags.find((t) => t.id === tagId)
				this.load(`page-tag-${this.pageId}-${tagId}`)
				try {
					await this.deleteTag(tag)
				} catch (e) {
					showError(t('collectives', 'Could not delete tag {name}', { name: tag.name }))
					throw (e)
				} finally {
					this.done(`page-tag-${this.pageId}-${tagId}`)
				}
			}
			showSuccess(n(
				'collectives',
				'Deleted %n tag',
				'Deleted %n tags',
				this.deletedTagIds.length,
			))
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

			const tag = this.sortedTags.find((t) => t.name === name)
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
		width: 100%;

		// Make switch full width
		:deep(.checkbox-radio-switch) {
			width: 100%;
			overflow: hidden;

			.checkbox-content {
				max-width: none;
				box-sizing: border-box;

				&__wrapper {
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
				}
			}
		}

		.tags-modal__name-form {
			// Align with NcCheckboxRadioSwitch
			margin-block: 4px;
			padding-left: 23px;
			flex-grow: 1;
		}

		.tags-modal__tag-title.deleted {
			text-decoration: line-through;
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
</style>
