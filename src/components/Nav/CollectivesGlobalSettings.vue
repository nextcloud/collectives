<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigationSettings :name="t('collectives', 'Collectives settings')">
		<NcTextField
			name="userFolder"
			:label="t('collectives', 'Collectives folder')"
			:label-visible="true"
			:model-value="userFolderValue"
			:disabled="disabledPicker"
			@click="selectCollectivesFolder" />
	</NcAppNavigationSettings>
</template>

<script>
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { NcAppNavigationSettings, NcTextField } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import { useSettingsStore } from '../../stores/settings.js'
import displayError from '../../util/displayError.js'

export default {
	name: 'CollectivesGlobalSettings',
	components: {
		NcAppNavigationSettings,
		NcTextField,
	},

	props: {
		networkOnline: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			collectivesFolderLoading: false,
		}
	},

	computed: {
		...mapState(useSettingsStore, ['collectivesFolder']),

		disabledPicker() {
			return this.collectivesFolderLoading || !this.networkOnline || !this.collectivesFolder
		},

		userFolderValue() {
			return this.disabledPicker
				? t('collectives', 'Create a collective first')
				: this.collectivesFolder
		},
	},

	methods: {
		t,

		...mapActions(useSettingsStore, ['updateCollectivesFolder']),

		async selectCollectivesFolder() {
			const picker = getFilePickerBuilder(t('collectives', 'Select location for collectives'))
				.setMultiSelect(false)
				.addButton({
					label: t('collectives', 'Choose'),
					variant: 'primary',
					callback: () => {},
				})
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.startAt(this.collectivesFolder)
				.build()
			const path = await picker.pick()

			// No root folder, has to start with `/`, not allowed to end with `/`
			if (path === '/'
				|| !path.startsWith('/')
				|| path.endsWith('/')) {
				const error = t('collectives', 'Invalid path selected. Only folders on first level are supported.')
				showError(error)
				throw new Error(error)
			}

			this.collectivesFolderLoading = true
			try {
				await this.updateCollectivesFolder(path)
			} catch (e) {
				console.error('Error updating collectives folder: ', e)
				displayError('Could not update collectives folder')(e)
			} finally {
				this.collectivesFolderLoading = false
			}
		},
	},
}
</script>
