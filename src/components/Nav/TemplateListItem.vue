<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="template-list-item">
		<div class="template-list-item-icon" @click.prevent.stop>
			<NcEmojiPicker
				:ref="`templateEmojiPicker-${template.id}`"
				:show-preview="true"
				:allow-unselect="true"
				:selected-emoji="template.emoji"
				@select="onSelectEmoji($event, template.id)"
				@unselect="onUnselectEmoji(template.id)">
				<NcButton
					variant="tertiary"
					:aria-label="t('collectives', 'Select emoji for template')"
					:title="t('collectives', 'Select emoji')"
					class="button-template-emoji"
					@click.prevent>
					<template #icon>
						<NcLoadingIcon
							v-if="isLoading(template.id)"
							:size="20"
							fill-color="var(--color-text-maxcontrast)" />
						<div v-else-if="template.emoji">
							{{ template.emoji }}
						</div>
						<PageTemplateIcon
							v-else
							:size="20"
							fill-color="var(--color-text-maxcontrast)" />
					</template>
				</NcButton>
			</NcEmojiPicker>
		</div>
		<div class="template-list-item-title">
			<form
				v-if="renameTitle !== null"
				class="template-list-item-title-text"
				@submit.prevent.stop="onRename"
				@click.prevent.stop>
				<NcTextField
					ref="renameField"
					v-model="renameTitle"
					v-click-outside="onRename"
					:placeholder="t('collectives', 'Template title')"
					:label-outside="true"
					:autofocus="true"
					:minlength="1"
					:required="true"
					@keyup.enter.prevent.stop
					@keyup.esc.prevent.stop="onStopRename" />
			</form>
			<a
				v-else
				href="#"
				class="template-list-item-title-text"
				@click="$emit('open', template)">
				{{ template.title }}
			</a>
		</div>
		<div class="template-action-buttons" @click.prevent.stop>
			<NcActions :force-menu="true">
				<NcActionButton
					:close-after-click="true"
					@click="onInitRename(template)">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('collectives', 'Rename') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onInitSelectEmoji(template.id)">
					<template #icon>
						<EmoticonIcon :size="20" />
					</template>
					{{ t('collectives', 'Select emoji') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
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
import { showError } from '@nextcloud/dialogs'
import { NcActionButton, NcActions, NcButton, NcEmojiPicker, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import { directive as ClickOutside } from 'v-click-outside'
import EmoticonIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import { useRootStore } from '../../stores/root.js'
import { useTemplatesStore } from '../../stores/templates.js'

export default {
	name: 'TemplateListItem',

	directives: {
		ClickOutside,
	},

	components: {
		DeleteIcon,
		EmoticonIcon,
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
			renameTitle: null,
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

		async onInitSelectEmoji(templateId) {
			this.$refs[`templateEmojiPicker-${templateId}`].open = true
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
			this.renameTitle = template.title
			this.$nextTick(() => {
				this.$refs.renameField.focus()
			})
		},

		async onRename() {
			try {
				await this.renameTemplate(this.template.id, this.renameTitle)
				this.onStopRename()
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename template'))
			}
		},

		onStopRename() {
			this.renameTitle = null
		},
	},
}
</script>

<style scoped lang="scss">
.template-list-item {
	display: flex;
	gap: calc(2 * var(--default-grid-baseline));

	height: var(--default-clickable-area);

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
