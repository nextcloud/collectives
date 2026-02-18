<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		contentClasses="attachment-rename-modal"
		isForm
		:open="open"
		size:normal
		:name="t('collectives', 'Rename attachment')"
		@update:open="$emit('update:open', $event)"
		@submit="$emit('attachmentRename', editedAttachmentName)">
		<NcTextField
			ref="nameInput"
			v-model="editedAttachmentName"
			class="attachment-rename-modal__input"
			:label="t('collectives', 'Attachment name')"
			:placeholder="t('collectives', 'Attachment name')" />

		<template #actions>
			<NcButton
				variant="primary"
				type="submit"
				:aria-label="t('collectives', 'Rename attachment')"
				:disabled="editedAttachmentName.trim() === '' && editedAttachmentName !== attachmentName">
				<template #icon>
					<CheckIcon :size="22" />
				</template>
				{{ t('collectives', 'Rename') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton, NcDialog, NcTextField } from '@nextcloud/vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

export default {
	name: 'AttachmentRenameDialog',

	components: {
		CheckIcon,
		NcButton,
		NcDialog,
		NcTextField,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},

		attachmentName: {
			type: String,
			required: true,
		},
	},

	emits: [
		'attachmentRename',
		'update:open',
	],

	data() {
		return {
			editedAttachmentName: '',
		}
	},

	watch: {
		open: {
			immediate: true,
			handler(open) {
				if (open) {
					this.startRenaming()
				}
				this.editedAttachmentName = this.attachmentName
			},
		},
	},

	methods: {
		t,

		startRenaming() {
			this.$nextTick(() => {
				const input = this.$refs.nameInput?.$el.querySelector('input')
				if (!input) {
					console.error('Could not find the rename input')
					return
				}
				input.focus()
				// calculate basename length of this.attachmentName
				const length = this.attachmentName.lastIndexOf('.')
				input.setSelectionRange(0, length)
			})
		},
	},
}
</script>

<style scoped lang="scss">
.attachment-rename-modal {
	&__input {
		margin-block-start: calc(2 * var(--default-grid-baseline));
	}
}
</style>
