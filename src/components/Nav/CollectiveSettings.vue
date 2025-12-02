<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSettingsDialog
		:open.sync="showSettings"
		:name="t('collectives', 'Collective settings')"
		:show-navigation="true">
		<NcAppSettingsSection id="name-and-emoji" :name="t('collectives', 'Name and emoji')">
			<div class="collective-name">
				<NcEmojiPicker
					:show-preview="true"
					:allow-unselect="true"
					:selected-emoji="collective.emoji"
					@select="updateEmoji"
					@unselect="unselectEmoji">
					<NcButton
						variant="tertiary"
						:aria-label="t('collectives', 'Select emoji for collective')"
						:class="{ loading: loading('updateCollectiveEmoji') || loading('renameCollective') }"
						class="button-emoji"
						@click.prevent>
						{{ collective.emoji }}
						<template v-if="!collective.emoji" #icon>
							<Emoticon :size="20" />
						</template>
					</NcButton>
				</NcEmojiPicker>
				<NcTextField
					v-model="newCollectiveName"
					:disabled="!isCollectiveOwner(collective)"
					:label="getRenameLabel"
					:error="isNameTooShort"
					:show-trailing-button="!isNameTooShort"
					trailing-button-icon="arrowEnd"
					class="collective-name-input"
					@blur="renameCollective()"
					@keypress.enter.prevent="renameCollective()"
					@trailing-button-click="renameCollective()" />
			</div>
			<div class="collective-name-error-placeholder">
				<div v-if="getNameError" class="collective-name-error">
					<AlertCircleIcon :size="16" />
					<label for="collective-name" class="modal-collective-name-error-label">
						{{ getNameError }}
					</label>
				</div>
			</div>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="permissions" :name="t('collectives', 'Permissions')">
			<div class="subsection-header">
				{{ t('collectives', 'Allow editing for') }}
			</div>

			<div class="permissions-input-edit">
				<NcCheckboxRadioSwitch
					v-model="editPermissions"
					:value="String(memberLevels.LEVEL_ADMIN)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_ADMIN))"
					name="edit_admins"
					type="radio">
					{{ t('collectives', 'Admins only') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="editPermissions"
					:value="String(memberLevels.LEVEL_MODERATOR)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_MODERATOR))"
					name="edit_moderators"
					type="radio">
					{{ t('collectives', 'Admins and moderators') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="editPermissions"
					:value="String(memberLevels.LEVEL_MEMBER)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_MEMBER))"
					name="edit_members"
					type="radio">
					{{ t('collectives', 'All members') }}
				</NcCheckboxRadioSwitch>
			</div>

			<div class="subsection-header subsection-header__second">
				{{ t('collectives', 'Allow sharing for') }}
			</div>

			<div class="permissions-input-share">
				<NcCheckboxRadioSwitch
					v-model="sharePermissions"
					:value="String(memberLevels.LEVEL_ADMIN)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_ADMIN))"
					name="share_admins"
					type="radio">
					{{ t('collectives', 'Admins only') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="sharePermissions"
					:value="String(memberLevels.LEVEL_MODERATOR)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_MODERATOR))"
					name="share_moderators"
					type="radio">
					{{ t('collectives', 'Admins and moderators') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="sharePermissions"
					:value="String(memberLevels.LEVEL_MEMBER)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_MEMBER))"
					name="share_members"
					type="radio">
					{{ t('collectives', 'All members') }}
				</NcCheckboxRadioSwitch>
			</div>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="page-settings" :name="t('collectives', 'Page settings')">
			<div class="subsection-header">
				{{ t('collectives', 'Default page mode') }}
			</div>

			<div class="edit-mode">
				<NcCheckboxRadioSwitch
					v-model="pageMode"
					:value="String(pageModes.MODE_VIEW)"
					:loading="loading('updateCollectivePageMode_' + String(pageModes.MODE_VIEW))"
					name="page_mode_view"
					type="radio">
					{{ t('collectives', 'View') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="pageMode"
					:value="String(pageModes.MODE_EDIT)"
					:loading="loading('updateCollectivePageMode_' + String(pageModes.MODE_EDIT))"
					name="page_mode_edit"
					type="radio">
					{{ t('collectives', 'Edit') }}
				</NcCheckboxRadioSwitch>
			</div>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="danger-zone" :name="t('collectives', 'Danger zone')">
			<div>
				<NcButton variant="error" :aria-label="t('collectives', 'Delete collective')" @click="onTrashCollective()">
					{{ t('collectives', 'Delete collective') }}
				</NcButton>
			</div>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { NcAppSettingsDialog, NcAppSettingsSection, NcButton, NcCheckboxRadioSwitch, NcEmojiPicker, NcTextField } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import Emoticon from 'vue-material-design-icons/EmoticonOutline.vue'
import { memberLevels, pageModes } from '../../constants.js'
import { useCirclesStore } from '../../stores/circles.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'
import displayError from '../../util/displayError.js'

export default {
	name: 'CollectiveSettings',

	components: {
		AlertCircleIcon,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcButton,
		NcCheckboxRadioSwitch,
		NcEmojiPicker,
		NcTextField,
		Emoticon,
	},

	props: {
		collective: {
			required: true,
			type: Object,
		},
	},

	data() {
		return {
			memberLevels,
			pageModes,
			newCollectiveName: this.collective.name,
			showSettings: true,
			editPermissions: String(this.collective.editPermissionLevel),
			sharePermissions: String(this.collective.sharePermissionLevel),
			pageMode: String(this.collective.pageMode),
			emoji: null,
		}
	},

	computed: {
		...mapState(useRootStore, [
			'collectiveId',
			'loading',
		]),

		...mapState(useCollectivesStore, ['isCollectiveOwner']),

		emojiTitle() {
			return this.collective.emoji ? t('collectives', 'Change emoji') : t('collectives', 'Add emoji')
		},

		getRenameLabel() {
			return this.isCollectiveOwner(this.collective)
				? t('collectives', 'Name of the collective')
				: t('collectives', 'Renaming is limited to owners of the team')
		},

		isNameTooShort() {
			return !!this.newCollectiveName && this.newCollectiveName.length < 3
		},

		getNameError() {
			if (this.isNameTooShort) {
				return t('collectives', 'Name too short, requires at least three characters')
			}
			return null
		},
	},

	watch: {
		showSettings(value) {
			if (!value) {
				this.setSettingsCollectiveId(null)
			}
		},

		editPermissions(val) {
			const permission = String(val)
			this.load('updateCollectiveEditPermissions_' + permission)
			this.updateCollectiveEditPermissions({ id: this.collective.id, level: parseInt(permission) }).then(() => {
				this.done('updateCollectiveEditPermissions_' + permission)
				showSuccess(t('collectives', 'Editing permissions updated'))
			}).catch((error) => {
				this.editPermissions = String(this.collective.editPermissionLevel)
				this.done('updateCollectiveEditPermissions_' + permission)
				showError(t('collectives', 'Could not update editing permissions'))
				throw error
			})
		},

		sharePermissions(val) {
			const permission = String(val)
			this.load('updateCollectiveSharePermissions_' + permission)
			this.updateCollectiveSharePermissions({ id: this.collective.id, level: parseInt(permission) }).then(() => {
				showSuccess(t('collectives', 'Sharing permissions updated'))
				this.done('updateCollectiveSharePermissions_' + permission)
			}).catch((error) => {
				this.sharePermissions = String(this.collective.sharePermissionLevel)
				this.done('updateCollectiveSharePermissions_' + permission)
				showError(t('collectives', 'Could not update sharing permissions'))
				throw error
			})
		},

		pageMode(val) {
			const pageMode = String(val)
			this.load('updateCollectivePageMode_' + pageMode)
			this.updateCollectivePageMode({ id: this.collective.id, mode: parseInt(pageMode) }).then(() => {
				this.done('updateCollectivePageMode_' + pageMode)
				showSuccess(t('collectives', 'Default page mode updated'))
			}).catch((error) => {
				this.pageMode = String(this.collective.pageMode)
				this.done('updateCollectivePageMode_' + pageMode)
				showError(t('collectives', 'Could not update default page mode'))
				throw error
			})
		},
	},

	methods: {
		...mapActions(useRootStore, [
			'load',
			'done',
		]),

		...mapActions(useCirclesStore, ['renameCircle']),
		...mapActions(useCollectivesStore, [
			'setSettingsCollectiveId',
			'trashCollective',
			'updateCollective',
			'updateCollectiveEditPermissions',
			'updateCollectiveSharePermissions',
			'updateCollectivePageMode',
		]),

		/**
		 * Update the emoji of a collective
		 *
		 * @param {string} emoji Emoji
		 */
		updateEmoji(emoji) {
			this.load('updateCollectiveEmoji')
			const collective = { id: this.collective.id }
			collective.emoji = emoji
			this.updateCollective(collective).then(() => {
				showSuccess(t('collectives', 'Emoji updated'))
				this.done('updateCollectiveEmoji')
			}).catch((error) => {
				showError(t('collectives', 'Could not update emoji for the collective'))
				this.done('updateCollectiveEmoji')
				throw error
			})
		},

		unselectEmoji() {
			return this.updateEmoji('')
		},

		/**
		 * Rename team and reload collective
		 */
		async renameCollective() {
			// Ignore rename to same name
			if (this.isNameTooShort || this.newCollectiveName === this.collective.name) {
				return
			}

			// Prevent duplicate requests by different events (e.g. blur + submit)
			if (this.loading('renameCollective')) {
				return
			}

			this.load('renameCollective')

			// If currentCollective is renamed, we need to update the router path later
			const redirect = this.collectiveId === this.collective.id

			// Wait for team rename (also patches store with updated collective and pages)
			const collective = { ...this.collective }
			collective.name = this.newCollectiveName
			await this.renameCircle(collective).then(() => {
				showSuccess('Collective renamed')
			}).catch((error) => {
				showError(t('collectives', 'Could not rename the collective'))
				this.done('renameCollective')
				throw error
			})

			// Name might have changed (due to team name conflicts), update input field
			this.newCollectiveName = this.collective.name

			// Push new router path if currentCollective was renamed
			if (redirect) {
				this.$router.go(0)
			}

			this.done('renameCollective')
		},

		/**
		 * Trash a collective with the given name
		 */
		onTrashCollective() {
			if (this.collectiveId === this.collective.id) {
				this.$router.push('/')
				emit('toggle-navigation', { open: true })
			}
			this.trashCollective(this.collective)
				.catch(displayError('Could not move the collective to trash'))
				.finally(() => {
					this.setSettingsCollectiveId(null)
				})
		},
	},
}
</script>

<style lang="scss" scoped>
button.button-emoji {
	padding: 0;
	font-size: 1.2em;
}

.collective-name {
	display: flex;
	gap: 4px;
	align-items: center;
	height: calc(var(--default-clickable-area) + 12px);

	.collective-name-input {
		display: grid;
		align-items: center;
		padding-block-end: 6px;
	}
}

.collective-name-error-placeholder {
	min-height: 24px;
}

.collective-name-error {
	display: flex;
	// Emoji button + input field padding
	padding-left: calc(57px + 12px);

	&-label {
		padding-left: 4px;
	}
}

.subsection-header {
	font-weight: bold;
	margin-bottom: 12px;
	&__second {
		margin-top: 12px;
	}
}
</style>
