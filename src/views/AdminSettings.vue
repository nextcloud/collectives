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
	<NcSettingsSection
		:name="t('collectives', 'Publish feature')"
		:description="t('collectives', 'Enable or disable the publish feature for collectives')">
		<NcCheckboxRadioSwitch
			id="isPublishFeatureEnabled"
			v-model="isPublishFeatureEnabled"
			type="switch"
			@update:modelValue="savePublishFeatureToggle">
			{{ t('collectives', 'Enable publish feature') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

interface AdminSettingsState {
	default_user_folder: string
	publish_enabled: boolean
}

const adminSettings = loadState<AdminSettingsState>('collectives', 'adminSettings')
let originalDefaultUserFolder = adminSettings.default_user_folder
const defaultUserFolder = ref(adminSettings.default_user_folder)
const isPublishFeatureEnabled = ref(adminSettings.publish_enabled)

const defaultUserFolderError = computed(() => {
	return defaultUserFolder.value !== ''
		&& !/^\/[a-zA-Z0-9-_./]+$/.test(defaultUserFolder.value)
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

/**
 * Saves the publish_enabled setting to the server
 */
function savePublishFeatureToggle() {
	globalThis.OCP.AppConfig.setValue('collectives', 'publish_enabled', isPublishFeatureEnabled.value ? 'true' : 'false', {
		success() {
			showSuccess(t('collectives', 'Publish feature setting saved'))
		},
	})
}
</script>
