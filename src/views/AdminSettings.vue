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
		<div class="publish-settings">
			<NcCheckboxRadioSwitch
				id="isPublishFeatureEnabled"
				v-model="isPublishFeatureEnabled"
				class="publish-switch"
				type="switch"
				@update:modelValue="savePublishFeatureToggle">
				{{ t('collectives', 'Enable publish feature') }}
			</NcCheckboxRadioSwitch>
			<template v-if="isPublishFeatureEnabled">
				<NcTextField
					id="staticSiteGeneratorUrl"
					v-model="staticSiteGeneratorUrl"
					name="staticSiteGeneratorUrl"
					:label="t('collectives', 'Static site generator URL')"
					:error="staticSiteGeneratorUrlError"
					:helperText="staticSiteGeneratorUrlHint"
					@keydown.enter="saveStaticSiteGeneratorUrl"
					@blur="saveStaticSiteGeneratorUrl" />
				<NcPasswordField
					id="staticSiteGeneratorToken"
					v-model="staticSiteGeneratorToken"
					name="staticSiteGeneratorToken"
					autocomplete="new-password"
					:label="t('collectives', 'Static site generator token')"
					@keydown.enter="saveStaticSiteGeneratorToken"
					@blur="saveStaticSiteGeneratorToken" />
				<NcButton :disabled="isCheckingHealth" @click="checkHealth">
					{{ t('collectives', 'Test connection') }}
				</NcButton>
				<p
					v-if="healthResult"
					class="health-result"
					:class="{ 'health-result--error': !healthResult.success }"
					role="status">
					{{ healthResult.message }}
				</p>
			</template>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { isAxiosError } from '@nextcloud/axios'
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { checkStaticSiteGeneratorHealth } from '../apis/collectives/staticSites.js'
import { isValidStaticSiteGeneratorUrl } from '../util/staticSiteGeneratorUrl.js'

interface AdminSettingsState {
	default_user_folder: string
	publish_enabled: boolean
	static_site_generator_url: string
	static_site_generator_token: string
}

const adminSettings = loadState<AdminSettingsState>('collectives', 'adminSettings')
let originalDefaultUserFolder = adminSettings.default_user_folder
const defaultUserFolder = ref(adminSettings.default_user_folder)
const isPublishFeatureEnabled = ref(adminSettings.publish_enabled)
let originalStaticSiteGeneratorUrl = adminSettings.static_site_generator_url
const staticSiteGeneratorUrl = ref(adminSettings.static_site_generator_url)
// A saved token is only provided as placeholder
let originalStaticSiteGeneratorToken = adminSettings.static_site_generator_token
const staticSiteGeneratorToken = ref(adminSettings.static_site_generator_token)
const isCheckingHealth = ref(false)
const healthResult = ref<{ success: boolean, message: string } | null>(null)

const defaultUserFolderError = computed(() => {
	return defaultUserFolder.value !== ''
		&& !/^\/[a-zA-Z0-9-_./]+$/.test(defaultUserFolder.value)
})

const defaultUserFolderHint = computed(() => {
	return defaultUserFolderError.value
		? t('collectives', 'Empty string or path starting with "/" is expected')
		: ''
})

const staticSiteGeneratorUrlError = computed(() => {
	return !isValidStaticSiteGeneratorUrl(staticSiteGeneratorUrl.value)
})

const staticSiteGeneratorUrlHint = computed(() => {
	return staticSiteGeneratorUrlError.value
		? t('collectives', 'URL starting with "https://" or "http://" is expected')
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

/**
 * Saves the static_site_generator_url setting to the server
 */
function saveStaticSiteGeneratorUrl() {
	if (staticSiteGeneratorUrlError.value || originalStaticSiteGeneratorUrl === staticSiteGeneratorUrl.value) {
		return
	}
	globalThis.OCP.AppConfig.setValue('collectives', 'static_site_generator_url', staticSiteGeneratorUrl.value, {
		success() {
			originalStaticSiteGeneratorUrl = staticSiteGeneratorUrl.value
			healthResult.value = null
			showSuccess(t('collectives', 'Saved static site generator URL'))
		},
	})
}

/**
 * Saves the static_site_generator_token setting to the server
 */
function saveStaticSiteGeneratorToken() {
	if (staticSiteGeneratorToken.value === '' || originalStaticSiteGeneratorToken === staticSiteGeneratorToken.value) {
		return
	}
	globalThis.OCP.AppConfig.setValue('collectives', 'static_site_generator_token', staticSiteGeneratorToken.value, {
		success() {
			originalStaticSiteGeneratorToken = staticSiteGeneratorToken.value
			healthResult.value = null
			showSuccess(t('collectives', 'Saved static site generator token'))
		},
	})
}

/**
 * Checks whether the static site generator is reachable with the saved settings
 */
async function checkHealth() {
	isCheckingHealth.value = true
	healthResult.value = null
	try {
		await checkStaticSiteGeneratorHealth()
		healthResult.value = { success: true, message: t('collectives', 'Static site generator is reachable') }
	} catch (error) {
		const message = isAxiosError(error) ? error.response?.data?.ocs?.data?.message : undefined
		healthResult.value = { success: false, message: message ?? t('collectives', 'Could not check the static site generator') }
	} finally {
		isCheckingHealth.value = false
	}
}
</script>

<style lang="scss" scoped>
.publish-settings {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: calc(var(--default-grid-baseline) * 4);
}

.publish-settings > :deep(.input-field) {
	width: 100%;
}

/* Align the switch with the fields below despite its inner padding */
.publish-switch {
	margin-inline-start: calc((var(--default-clickable-area) - var(--icon-height)) / -2);
}

.health-result {
	color: var(--color-success-text);
}

.health-result--error {
	color: var(--color-error-text);
}
</style>
