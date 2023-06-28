<template>
	<NcAppSettingsDialog :open.sync="showSettings"
		:title="t('collectives', 'Collective settings')"
		:show-navigation="true">
		<NcAppSettingsSection id="name-and-emoji" :title="t('collectives', 'Name and emoji')">
			<div class="collective-name">
				<NcEmojiPicker :show-preview="true" @select="updateEmoji">
					<NcButton type="tertiary"
						:aria-label="t('collectives', 'Select emoji for collective')"
						:title="emojiTitle"
						:class="{'loading': loading('updateCollectiveEmoji') || loading('renameCollective')}"
						class="button-emoji"
						@click.prevent>
						{{ collective.emoji }}
						<template v-if="!collective.emoji" #icon>
							<EmoticonOutline :size="20" />
						</template>
					</NcButton>
				</NcEmojiPicker>
				<NcTextField ref="collectiveName"
					:value.sync="newCollectiveName"
					:disabled="!isCollectiveOwner(collective)"
					:label="getRenameLabel"
					:error="isNameTooShort"
					:show-trailing-button="!isNameTooShort"
					trailing-button-icon="arrowRight"
					class="collective-name-input"
					@blur="renameCollective()"
					@keypress.enter.prevent="renameCollective()"
					@trailing-button-click="renameCollective()" />
			</div>
			<div class="collective-name-error-placeholder">
				<div v-if="getNameError" class="collective-name-error">
					<AlertCircleOutlineIcon :size="16" />
					<label for="collective-name" class="modal-collective-name-error-label">
						{{ getNameError }}
					</label>
				</div>
			</div>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="permissions" :title="t('collectives', 'Permissions')">
			<div class="subsection-header">
				{{ t('collectives', 'Allow editing for') }}
			</div>

			<div class="permissions-input-edit">
				<NcCheckboxRadioSwitch :checked.sync="editPermissions"
					:value="String(memberLevels.LEVEL_ADMIN)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_ADMIN))"
					name="edit_admins"
					type="radio">
					{{ t('collectives', 'Admins only') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="editPermissions"
					:value="String(memberLevels.LEVEL_MODERATOR)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_MODERATOR))"
					name="edit_moderators"
					type="radio">
					{{ t('collectives', 'Admins and moderators') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="editPermissions"
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
				<NcCheckboxRadioSwitch :checked.sync="sharePermissions"
					:value="String(memberLevels.LEVEL_ADMIN)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_ADMIN))"
					name="share_admins"
					type="radio">
					{{ t('collectives', 'Admins only') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="sharePermissions"
					:value="String(memberLevels.LEVEL_MODERATOR)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_MODERATOR))"
					name="share_moderators"
					type="radio">
					{{ t('collectives', 'Admins and moderators') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="sharePermissions"
					:value="String(memberLevels.LEVEL_MEMBER)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_MEMBER))"
					name="share_members"
					type="radio">
					{{ t('collectives', 'All members') }}
				</NcCheckboxRadioSwitch>
			</div>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="page-settings" :title="t('collectives', 'Page settings')">
			<div class="subsection-header">
				{{ t('collectives', 'Default page mode') }}
			</div>

			<div class="edit-mode">
				<NcCheckboxRadioSwitch :checked.sync="pageMode"
					:value="String(pageModes.MODE_VIEW)"
					:loading="loading('updateCollectivePageMode_' + String(pageModes.MODE_VIEW))"
					name="page_mode_view"
					type="radio">
					{{ t('collectives', 'View') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="pageMode"
					:value="String(pageModes.MODE_EDIT)"
					:loading="loading('updateCollectivePageMode_' + String(pageModes.MODE_EDIT))"
					name="page_mode_edit"
					type="radio">
					{{ t('collectives', 'Edit') }}
				</NcCheckboxRadioSwitch>
			</div>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="members" :title="t('collectives', 'Members')">
			<div class="section-description">
				{{ t('collectives', 'Members can be managed via the connected circle in the Contacts app.') }}
			</div>
			<div>
				<NcButton v-tooltip="membersDisabledTooltip"
					:aria-label="t('collectives', 'Open circle in Contacts')"
					:disabled="!isContactsInstalled"
					@click="openCircleLink">
					{{ t('collectives', 'Open circle in Contacts') }}
				</NcButton>
			</div>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="danger-zone" :title="t('collectives', 'Danger zone')">
			<div>
				<NcButton type="error" :aria-label="t('collectives', 'Delete collective')" @click="trashCollective()">
					{{ t('collectives', 'Delete collective') }}
				</NcButton>
			</div>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { memberLevels, pageModes } from '../../constants.js'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcAppSettingsDialog, NcAppSettingsSection, NcButton, NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import AlertCircleOutlineIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import { generateUrl } from '@nextcloud/router'
import {
	RENAME_CIRCLE,
	UPDATE_COLLECTIVE,
	TRASH_COLLECTIVE,
	UPDATE_COLLECTIVE_EDIT_PERMISSIONS,
	UPDATE_COLLECTIVE_SHARE_PERMISSIONS,
	UPDATE_COLLECTIVE_PAGE_MODE,
} from '../../store/actions.js'
import displayError from '../../util/displayError.js'

export default {
	name: 'CollectiveSettings',

	components: {
		AlertCircleOutlineIcon,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcButton,
		NcCheckboxRadioSwitch,
		NcEmojiPicker,
		NcTextField,
		EmoticonOutline,
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
		}
	},

	computed: {
		...mapState({
			circles: (state) => state.circles.circles,
			pages: (state) => state.pages.pages,
		}),

		...mapGetters([
			'collectiveParam',
			'pageParam',
			'isCollectiveOwner',
			'loading',
		]),

		emojiTitle() {
			return this.collective.emoji ? t('collectives', 'Change emoji') : t('collectives', 'Add emoji')
		},

		getRenameLabel() {
			return this.isCollectiveOwner(this.collective)
				? t('collectives', 'Name of the collective')
				: t('collectives', 'Renaming is limited to owners of the circle')
		},

		membersDisabledTooltip() {
			return !this.isContactsInstalled
				&& t('collectives', 'The contacts app is required to manage members')
		},

		isContactsInstalled() {
			return 'contacts' in this.OC.appswebroots
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
			this.dispatchUpdateCollectiveEditPermissions({ id: this.collective.id, level: parseInt(permission) }).then(() => {
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
			this.dispatchUpdateCollectiveSharePermissions({ id: this.collective.id, level: parseInt(permission) }).then(() => {
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
			this.dispatchUpdateCollectivePageMode({ id: this.collective.id, mode: parseInt(pageMode) }).then(() => {
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
		...mapMutations([
			'load',
			'done',
			'setSettingsCollectiveId',
		]),

		...mapActions({
			dispatchRenameCircle: RENAME_CIRCLE,
			dispatchUpdateCollective: UPDATE_COLLECTIVE,
			dispatchTrashCollective: TRASH_COLLECTIVE,
			dispatchUpdateCollectiveEditPermissions: UPDATE_COLLECTIVE_EDIT_PERMISSIONS,
			dispatchUpdateCollectiveSharePermissions: UPDATE_COLLECTIVE_SHARE_PERMISSIONS,
			dispatchUpdateCollectivePageMode: UPDATE_COLLECTIVE_PAGE_MODE,
		}),

		/**
		 * Update the emoji of a collective
		 *
		 * @param {string} emoji Emoji
		 */
		updateEmoji(emoji) {
			this.load('updateCollectiveEmoji')
			const collective = { id: this.collective.id }
			collective.emoji = emoji
			this.dispatchUpdateCollective(collective).then(() => {
				showSuccess(t('collectives', 'Emoji updated'))
				this.done('updateCollectiveEmoji')
			}).catch((error) => {
				showError(t('collectives', 'Could not update emoji for the collective'))
				this.done('updateCollectiveEmoji')
				throw error
			})
		},

		/**
		 * Rename circle and reload collective
		 */
		async renameCollective() {
			// Ignore rename to same name
			if (this.isNameTooShort || this.newCollectiveName === this.collective.name) {
				return
			}

			this.load('renameCollective')

			// If currentCollective is renamed, we need to update the router path later
			const redirect = this.collectiveParam === this.collective.name

			// Wait for circle rename (also patches store with updated collective and pages)
			const collective = { ...this.collective }
			collective.name = this.newCollectiveName
			await this.dispatchRenameCircle(collective).then(() => {
				showSuccess('Collective renamed')
			}).catch((error) => {
				showError(t('collectives', 'Could not rename the collective'))
				this.done('renameCollective')
				throw error
			})

			// Name might have changed (due to circle name conflicts), update input field
			this.newCollectiveName = this.collective.name

			// Push new router path if currentCollective was renamed
			if (redirect) {
				this.$router.push(
					'/' + encodeURIComponent(this.newCollectiveName)
					+ (this.pageParam ? '/' + this.pageParam : ''),
				)
			}

			this.done('renameCollective')
		},

		openCircleLink() {
			window.open(generateUrl('/apps/contacts/direct/circle/' + this.collective.circleId), '_blank')
		},

		/**
		 * Trash a collective with the given name
		 */
		trashCollective() {
			if (this.collectiveParam === this.collective.name) {
				this.$router.push('/')
			}
			this.dispatchTrashCollective(this.collective)
				.catch(displayError('Could not move the collective to trash'))
				.finally(this.setSettingsCollectiveId(null))
		},
	},
}
</script>

<style lang="scss" scoped>
.button-emoji {
	font-size: 20px;
}

.collective-name {
	display: flex;

	.collective-name-input {
		display: grid;
		align-items: center;
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

.section-description {
	margin-bottom: 12px;
}
</style>
