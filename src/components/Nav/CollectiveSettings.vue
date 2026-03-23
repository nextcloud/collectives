<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSettingsDialog
		v-model:open="showSettings"
		:name="t('collectives', 'Collective settings')"
		showNavigation>
		<NcAppSettingsSection id="name-and-emoji" :name="t('collectives', 'Name and emoji')">
			<NcFormGroup :label="t('collectives', 'Name and emoji')" hideLabel>
				<div class="collective-name-and-emoji">
					<NcEmojiPicker
						showPreview
						allowUnselect
						:selectedEmoji="collective.emoji"
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
						:label="t('collectives', 'Name of the collective')"
						:error="isNameTooShort"
						:showTrailingButton="!isNameTooShort"
						trailingButtonIcon="arrowEnd"
						class="collective-name-input"
						@blur="renameCollective()"
						@keydown.enter.prevent="renameCollective()"
						@trailingButtonClick="renameCollective()" />
				</div>
				<div class="collective-name-error-placeholder">
					<div v-if="getNameError" class="collective-name-error">
						<AlertCircleIcon :size="16" />
						<label for="collective-name" class="modal-collective-name-error-label">
							{{ getNameError }}
						</label>
					</div>
				</div>
			</NcFormGroup>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="permissions" :name="t('collectives', 'Permissions')">
			<NcRadioGroup v-model="editPermissions" :label="t('collectives', 'Allow editing for')" class="edit-permissions">
				<NcRadioGroupButton :label="t('collectives', 'Admins only')" :value="String(memberLevels.LEVEL_ADMIN)">
					<template #icon>
						<CrownIcon />
					</template>
				</NcRadioGroupButton>
				<NcRadioGroupButton :label="t('collectives', 'Admins and moderators')" :value="String(memberLevels.LEVEL_MODERATOR)">
					<template #icon>
						<AccountCogIcon />
					</template>
				</NcRadioGroupButton>
				<NcRadioGroupButton :label="t('collectives', 'All members')" :value="String(memberLevels.LEVEL_MEMBER)">
					<template #icon>
						<AccountIcon />
					</template>
				</NcRadioGroupButton>
			</NcRadioGroup>

			<NcRadioGroup v-model="sharePermissions" :label="t('collectives', 'Allow sharing for')" class="share-permissions">
				<NcRadioGroupButton :label="t('collectives', 'Admins only')" :value="String(memberLevels.LEVEL_ADMIN)">
					<template #icon>
						<CrownIcon />
					</template>
				</NcRadioGroupButton>
				<NcRadioGroupButton :label="t('collectives', 'Admins and moderators')" :value="String(memberLevels.LEVEL_MODERATOR)">
					<template #icon>
						<AccountCogIcon />
					</template>
				</NcRadioGroupButton>
				<NcRadioGroupButton :label="t('collectives', 'All members')" :value="String(memberLevels.LEVEL_MEMBER)">
					<template #icon>
						<AccountIcon />
					</template>
				</NcRadioGroupButton>
			</NcRadioGroup>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="page-settings" :name="t('collectives', 'Page settings')">
			<NcRadioGroup v-model="pageMode" :label="t('collectives', 'Default Page Mode')" class="page-mode">
				<NcRadioGroupButton :label="t('collectives', 'Preview')" :value="String(pageModes.MODE_PREVIEW)">
					<template #icon>
						<EyeIcon />
					</template>
				</NcRadioGroupButton>
				<NcRadioGroupButton :label="t('collectives', 'Edit')" :value="String(pageModes.MODE_EDIT)">
					<template #icon>
						<PencilIcon />
					</template>
				</NcRadioGroupButton>
			</NcRadioGroup>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="danger-zone" :name="t('collectives', 'Danger zone')">
			<NcFormGroup
				:label="t('collectives', 'Delete collective')"
				hideLabel
				:description="t('collectives', 'Deleted collectives can be restored from the collectives trash.')">
				<NcButton
					variant="error"
					wide
					:aria-label="t('collectives', 'Delete collective')"
					@click="onTrashCollective()">
					{{ t('collectives', 'Delete collective') }}
				</NcButton>
			</NcFormGroup>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AccountCogIcon from 'vue-material-design-icons/AccountCogOutline.vue'
import AccountIcon from 'vue-material-design-icons/AccountOutline.vue'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import CrownIcon from 'vue-material-design-icons/CrownOutline.vue'
import Emoticon from 'vue-material-design-icons/EmoticonOutline.vue'
import EyeIcon from 'vue-material-design-icons/EyeOutline.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import { memberLevels, pageModes } from '../../constants.js'
import { useCirclesStore } from '../../stores/circles.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'
import displayError from '../../util/displayError.js'

export default {
	name: 'CollectiveSettings',

	components: {
		AccountCogIcon,
		AccountIcon,
		AlertCircleIcon,
		CrownIcon,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcButton,
		NcEmojiPicker,
		NcFormGroup,
		NcRadioGroup,
		NcRadioGroupButton,
		NcTextField,
		Emoticon,
		EyeIcon,
		PencilIcon,
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

		emojiTitle() {
			return this.collective.emoji ? t('collectives', 'Change emoji') : t('collectives', 'Add emoji')
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
		t,

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
		async onTrashCollective() {
			if (this.collectiveId === this.collective.id) {
				await this.$router.push('/')
				emit('toggle-navigation', { open: true })
			}
			try {
				await this.trashCollective(this.collective)
				emit('collectives:navigation:collective-trashed')
			} catch (e) {
				displayError('Could not move the collective to trash')(e)
			} finally {
				this.setSettingsCollectiveId(null)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
button.button-emoji {
	padding: 0;
	font-size: 1.2em;
}

.collective-name-and-emoji {
	display: flex;
	flex-direction: row;
	align-items: center;
}

.collective-name-error-placeholder {
	min-height: 22px;
}

.collective-name-error {
	display: flex;
	gap: var(--default-grid-baseline);
	// Emoji button + input field padding
	padding-inline-start: calc(var(--default-clickable-area) + var(--input-padding-start));
}
</style>
