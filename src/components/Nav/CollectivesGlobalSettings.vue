<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigationSettings :name="t('collectives', 'Collectives settings')">
		<NcTextField name="userFolder"
			:label="t('collectives', 'Collectives Folder')"
			:label-visible="true"
			:value="userFolderValue"
			:disabled="disabledPicker"
			@click="selectCollectivesFolder" />
	</NcAppNavigationSettings>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import { useSettingsStore } from '../../stores/settings.js'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { NcAppNavigationSettings, NcTextField } from '@nextcloud/vue'
import displayError from '../../util/displayError.js'

export default {
	name: 'CollectivesGlobalSettings',
	components: {
		NcAppNavigationSettings,
		NcTextField,
	},

	data() {
		return {
			collectivesFolderLoading: false,
		}
	},

	computed: {
		...mapState(useSettingsStore, ['collectivesFolder']),

		disabledPicker() {
			return this.collectivesFolderLoading || !this.collectivesFolder
		},

		userFolderValue() {
			return this.disabledPicker
				? t('collectives', 'Create a collective first')
				: this.collectivesFolder
		},
	},

	methods: {
		...mapActions(useSettingsStore, ['updateCollectivesFolder']),

		selectCollectivesFolder() {
			const picker = getFilePickerBuilder(t('collectives', 'Select location for collectives'))
				.setMultiSelect(false)
				.setType(1)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.startAt(this.collectivesFolder)
				.build()
			picker.pick()
				.then((path) => {
					// No root folder, has to start with `/`, no subfolder
					if (path === '/'
						|| !path.startsWith('/')
						|| path.includes('/', 1)) {
						const error = t('collectives', 'Invalid path selected. Only folders on first level are supported.')
						showError(error)
						throw new Error(error)
					}

					this.collectivesFolderLoading = true
					this.updateCollectivesFolder(path)
						.catch(displayError('Could not update collectives folder'))
					this.collectivesFolderLoading = false
				})
		},
	},
}
</script>
