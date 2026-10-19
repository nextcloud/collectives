<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:open
		isForm
		size="normal"
		:name="t('collectives', 'Compare versions')"
		@update:open="emit('update:open', $event)"
		@submit="onSubmit">
		<div class="version-compare-dialog">
			<NcSelect
				v-model="earlier"
				:options
				:inputLabel="t('collectives', 'Earlier version')"
				:clearable="false" />
			<NcSelect
				v-model="later"
				:options
				:inputLabel="t('collectives', 'Later version')"
				:clearable="false" />
		</div>

		<template #actions>
			<NcButton
				variant="primary"
				type="submit"
				:disabled="!earlier || !later || earlier === later">
				{{ t('collectives', 'Compare') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'

export interface VersionOption {
	label: string
	version: { mtime: number }
}

const props = defineProps<{
	open: boolean
	/** Versions to choose from, the latest first */
	options: VersionOption[]
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	compare: [first: VersionOption['version'], second: VersionOption['version']]
}>()

const earlier = ref<VersionOption | null>(null)
const later = ref<VersionOption | null>(null)

// Start with the previous and the latest version
watch(() => props.open, (open) => {
	if (open) {
		earlier.value = props.options[1] ?? null
		later.value = props.options[0] ?? null
	}
}, { immediate: true })

/**
 * Compare the selected versions
 */
function onSubmit() {
	if (!earlier.value || !later.value || earlier.value === later.value) {
		return
	}
	emit('compare', earlier.value.version, later.value.version)
	emit('update:open', false)
}
</script>

<style lang="scss" scoped>
.version-compare-dialog {
	display: flex;
	flex-direction: column;
	gap: calc(2 * var(--default-grid-baseline));
	padding-block-end: calc(2 * var(--default-grid-baseline));
}
</style>
