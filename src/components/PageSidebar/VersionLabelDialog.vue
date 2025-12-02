<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		content-classes="version-label-modal"
		is-form
		:open="open"
		size:normal
		:name="t('collectives', 'Name this version')"
		@update:open="$emit('update:open', $event)"
		@submit="$emit('label-update', editedVersionLabel)">
		<NcTextField
			ref="labelInput"
			v-model="editedVersionLabel"
			class="version-label-modal__input"
			:label="t('collectives', 'Version name')"
			:placeholder="t('collectives', 'Version name')" />

		<p class="version-label-modal__info">
			{{ t('files_versions', 'Named versions are persisted, and excluded from automatic cleanups when your storage quota is full.') }}
		</p>

		<template #actions>
			<NcButton
				v-if="versionLabel"
				variant="error"
				type="reset"
				:aria-label="t('collectives', 'Remove version name')"
				@click="$emit('label-update', '')">
				{{ t('collectives', 'Remove version name') }}
			</NcButton>
			<NcButton
				variant="primary"
				type="submit"
				:aria-label="t('collectives', 'Save version name')"
				:disabled="editedVersionLabel.trim() === ''">
				<template #icon>
					<CheckIcon :size="22" />
				</template>
				{{ t('collectives', 'Save version name') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcTextField } from '@nextcloud/vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

export default {
	name: 'VersionLabelDialog',

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

		versionLabel: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			editedVersionLabel: '',
		}
	},

	watch: {
		versionLabel: {
			immediate: true,
			handler(label) {
				this.editedVersionLabel = label ?? ''
			},
		},

		open: {
			immediate: true,
			handler(open) {
				if (open) {
					this.$nextTick(() => (this.$refs.labelInput).focus())
				}
				this.editedVersionLabel = this.versionLabel
			},
		},
	},
}
</script>

<style scoped lang="scss">
.version-label-modal {
	&__info {
		color: var(--color-text-maxcontrast);
		margin-block: calc(3 * var(--default-grid-baseline));
	}

	&__input {
		margin-block-start: calc(2 * var(--default-grid-baseline));
	}
}
</style>
