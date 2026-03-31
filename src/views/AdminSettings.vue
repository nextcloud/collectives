<!--
  * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  * SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('collectives', 'Collectives default user folder')"
		:description="t('collectives', 'The default path where collectives are mounted in user home directory')">
		<NcTextField
			id="defaultUserFolder"
			v-model="defaultUserFolder"
			name="defaultUserFolder"
			:label="t('collectives', 'Default user folder')"
			:error="defaultUserFolderError"
			:helperText="defaultUserFolderHint"
			@keydown.enter="saveDefaultUserFolder"
			@blur="saveDefaultUserFolder" />
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const adminSettings = loadState<{ default_user_folder: string }>('collectives', 'adminSettings')
let originalDefaultUserFolder = adminSettings.default_user_folder
const defaultUserFolder = ref(adminSettings.default_user_folder)

const defaultUserFolderError = computed(() => {
	return defaultUserFolder.value !== ''
		&& !/^\/[a-zA-Z0-9-_/]+$/.test(defaultUserFolder.value)
})

const defaultUserFolderHint = computed(() => {
	return defaultUserFolderError.value
		? t('collectives', 'Empty string or path starting with "/" is expected')
		: ''
})

/**
 * Saves the default_user_folder setting to the server
 */
async function saveDefaultUserFolder() {
	if (defaultUserFolderError.value || originalDefaultUserFolder === defaultUserFolder.value) {
		return
	}
	globalThis.OCP.AppConfig.setValue('collectives', 'default_user_folder', defaultUserFolder.value, {
		success() {
			originalDefaultUserFolder = defaultUserFolder.value
			showSuccess(t('collectives', 'Saved default user folder'))
		},
	})
}
</script>
