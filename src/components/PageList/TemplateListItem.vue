<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="template-list-item">
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
			<form v-if="renameName !== null"
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
				href="#"
				class="template-list-item-title-text"
				@click="$emit('open', template)">
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
					@click="$emit('delete', template)">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ deleteString }}
				</NcActionButton>
			</NcActions>
		</div>
	</li>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { useTemplatesStore } from '../../stores/templates.js'
import { showError } from '@nextcloud/dialogs'

import { NcActionButton, NcActions, NcButton, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'

import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'

export default {
	name: 'TemplateListItem',

	components: {
		DeleteIcon,
		NcActionButton,
		NcActions,
		NcButton,
		NcEmojiPicker,
		NcLoadingIcon,
		NcTextField,
		PageTemplateIcon,
		PencilIcon,
	},

	props: {
		template: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			renameName: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useTemplatesStore, ['hasSubpages']),

		isLoading() {
			return (templateId) => {
				return this.loading(`templateRename-${templateId}`) || this.loading(`templateEmoji-${templateId}`)
			}
		},

		deleteString() {
			return this.hasSubpages(this.template.id)
				? t('collectives', 'Delete template and subpages')
				: t('collectives', 'Delete')
		},
	},

	methods: {
		...mapActions(useTemplatesStore, [
			'renameTemplate',
			'setTemplateEmoji',
		]),

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
			this.$nextTick(() => {
				this.$refs.renameField.focus()
			})
		},

		async onRename() {
			try {
				await this.renameTemplate(this.template.id, this.renameName)
				this.onStopRename()
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename template'))
			}
		},

		onStopRename() {
			this.renameName = null
		},
	},
}
</script>

<style scoped lang="scss">
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
</style>
