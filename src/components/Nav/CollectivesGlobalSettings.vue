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
import { mapActions, mapState } from 'vuex'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { NcAppNavigationSettings, NcTextField } from '@nextcloud/vue'
import { UPDATE_COLLECTIVES_FOLDER } from '../../store/actions.js'
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
		...mapState({
			collectivesFolder: (state) => state.settings.collectivesFolder,
		}),
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
		...mapActions({
			dispatchUpdateCollectivesFolder: UPDATE_COLLECTIVES_FOLDER,
		}),

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
					this.dispatchUpdateCollectivesFolder(path)
						.catch(displayError('Could not update collectives folder'))
					this.collectivesFolderLoading = false
				})
		},
	},
}
</script>
