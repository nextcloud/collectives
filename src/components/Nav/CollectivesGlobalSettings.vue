<template>
	<AppNavigationSettings :title="t('collectives', 'Collectives settings')">
		<div>
			<p>
				<label for="userFolder">
					{{ t('collectives', 'Collectives Folder') }}
				</label>
			</p>
			<input id="userFolder"
				type="text"
				name="userFolder"
				class="user_folder__input"
				:value="userFolderValue"
				:disabled="disabledPicker"
				@click="selectCollectivesFolder">
		</div>
	</AppNavigationSettings>
</template>

<script>
import { mapActions, mapState } from 'vuex'
import { UPDATE_COLLECTIVES_FOLDER } from '../../store/actions.js'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import displayError from '../../util/displayError.js'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'

export default {
	name: 'CollectivesGlobalSettings',
	components: {
		AppNavigationSettings,
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
				.setModal(true)
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

<style lang="scss">
::v-deep .modal-container {
	display: flex !important;
}

.user_folder__input {
	width: 93%
}
</style>
