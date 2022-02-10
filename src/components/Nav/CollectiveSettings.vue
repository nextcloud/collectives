<template>
	<AppSettingsDialog
		:open.sync="showSettings"
		:aria-label="t('collectives', 'Collective settings')"
		:show-navigation="true">
		<AppSettingsSection :title="t('collectives', 'Details')">
			<div class="collective-name">
				<EmojiPicker
					:show-preview="true"
					@select="updateEmoji">
					<button class="emoji"
						type="button"
						:aria-label="emojiTitle"
						:aria-haspopup="true"
						:title="emojiTitle"
						:class="{'loading': loading('updateCollectiveEmoji')}"
						@click.prevent>
						<span v-if="collective.emoji">{{ collective.emoji }}</span>
						<EmoticonOutline v-else :size="20" />
					</button>
				</EmojiPicker>
				<form @submit.prevent.stop="renameCollective()">
					<input ref="nameField"
						v-model="newCollectiveName"
						v-tooltip="renameDisabledTooltip"
						type="text"
						:disabled="!isCollectiveOwner(collective)"
						required>
					<input v-tooltip="renameDisabledTooltip"
						type="submit"
						value=""
						class="icon-confirm"
						:class="{ 'icon-loading-small': loading('renameCollective') }"
						:disabled="!isCollectiveOwner(collective)">
				</form>
			</div>
		</AppSettingsSection>

		<AppSettingsSection :title="t('collectives', 'Permissions')">
			<div class="subsection-header">
				{{ t('collectives', 'Allow editing for') }}
			</div>

			<div class="permissions-input-edit">
				<CheckboxRadioSwitch
					:checked.sync="editPermissions"
					:value="String(memberLevels.LEVEL_ADMIN)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_ADMIN))"
					name="edit_admins"
					type="radio">
					{{ t('collectives', 'Admins only') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch
					:checked.sync="editPermissions"
					:value="String(memberLevels.LEVEL_MODERATOR)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_MODERATOR))"
					name="edit_moderators"
					type="radio">
					{{ t('collectives', 'Admins and moderaters') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch
					:checked.sync="editPermissions"
					:value="String(memberLevels.LEVEL_MEMBER)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_MEMBER))"
					name="edit_members"
					type="radio">
					{{ t('collectives', 'All members') }}
				</CheckboxRadioSwitch>
			</div>

			<div class="subsection-header subsection-header__second">
				{{ t('collectives', 'Allow sharing for') }}
			</div>

			<div class="permissions-input-share">
				<CheckboxRadioSwitch
					:checked.sync="sharePermissions"
					:value="String(memberLevels.LEVEL_ADMIN)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_ADMIN))"
					name="share_admins"
					type="radio">
					{{ t('collectives', 'Admins only') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch
					:checked.sync="sharePermissions"
					:value="String(memberLevels.LEVEL_MODERATOR)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_MODERATOR))"
					name="share_moderators"
					type="radio">
					{{ t('collectives', 'Admins and moderaters') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch
					:checked.sync="sharePermissions"
					:value="String(memberLevels.LEVEL_MEMBER)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_MEMBER))"
					name="share_members"
					type="radio">
					{{ t('collectives', 'All members') }}
				</CheckboxRadioSwitch>
			</div>
		</AppSettingsSection>

		<AppSettingsSection :title="t('collectives', 'Members')">
			<div>
				{{ t('collectives', 'Members can be managed via the connected circle in the Contacts app.') }}
			</div>
			<div>
				<!-- TODO: Use secondary button from @nextcloud/vue 5.0 once it's there -->
				<button v-tooltip="membersDisabledTooltip"
					class="button"
					:disabled="!isContactsInstalled"
					@click="openCircleLink">
					{{ t('collectives', 'Open circle in Contacts') }}
				</button>
			</div>
		</AppSettingsSection>

		<AppSettingsSection :title="t('collectives', 'Danger zone')">
			<div>
				<button class="error primary" @click="trashCollective()">
					{{ t('collectives', 'Delete collective') }}
				</button>
			</div>
		</AppSettingsSection>
	</AppSettingsDialog>
</template>

<script>
import { memberLevels } from '../../constants'
import { mapGetters, mapMutations, mapState } from 'vuex'
import { showError, showSuccess } from '@nextcloud/dialogs'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import { generateUrl } from '@nextcloud/router'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline'
import {
	RENAME_CIRCLE,
	UPDATE_COLLECTIVE,
	TRASH_COLLECTIVE,
	UPDATE_COLLECTIVE_EDIT_PERMISSIONS,
	UPDATE_COLLECTIVE_SHARE_PERMISSIONS,
} from '../../store/actions'
import displayError from '../../util/displayError'

export default {
	name: 'CollectiveSettings',

	components: {
		AppSettingsDialog,
		AppSettingsSection,
		EmojiPicker,
		EmoticonOutline,
		CheckboxRadioSwitch,
	},

	directives: {
		Tooltip,
	},

	props: {
		collective: {
			required: true,
			type: Object,
		},
		open: {
			required: true,
			type: Boolean,
		},
	},

	data() {
		return {
			memberLevels,
			newCollectiveName: this.collective.name,
			showSettings: false,
			editPermissions: String(this.collective.editPermissionLevel),
			sharePermissions: String(this.collective.sharePermissionLevel),
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

		renameDisabledTooltip() {
			return !this.isCollectiveOwner(this.collective)
				&& t('collectives', 'Renaming is limited to owners of the circle')
		},

		membersDisabledTooltip() {
			return !this.isContactsInstalled
				&& t('collectives', 'The contacts app is required to manage members')
		},

		isContactsInstalled() {
			return 'contacts' in this.OC.appswebroots
		},
	},

	watch: {
		showSettings(value) {
			if (!value) {
				this.$emit('update:open', value)
			}
		},
		open(value) {
			if (value) {
				this.showSettings = true
			}
		},
		editPermissions(val) {
			const permission = String(val)
			this.load('updateCollectiveEditPermissions_' + permission)
			this.$store.dispatch(UPDATE_COLLECTIVE_EDIT_PERMISSIONS, { id: this.collective.id, level: parseInt(permission) }).then(() => {
				this.done('updateCollectiveEditPermissions_' + permission)
				showSuccess(t('collectives', 'Editing permissions updated'))
			}).catch((error) => {
				this.editPermissions = String(this.collective.editPermissionLevel)
				this.done('updateCollectiveEditPermissions_' + permission)
				showError('Could not update editing permissions')
				throw error
			})
		},
		sharePermissions(val) {
			const permission = String(val)
			this.load('updateCollectiveSharePermissions_' + permission)
			this.$store.dispatch(UPDATE_COLLECTIVE_SHARE_PERMISSIONS, { id: this.collective.id, level: parseInt(permission) }).then(() => {
				showSuccess(t('collectives', 'Sharing permissions updated'))
				this.done('updateCollectiveSharePermissions_' + permission)
			}).catch((error) => {
				this.sharePermissions = String(this.collective.sharePermissionLevel)
				this.done('updateCollectiveSharePermissions_' + permission)
				showError('Could not update sharing permissions')
				throw error
			})
		},
	},

	methods: {
		...mapMutations(['load', 'done']),

		/**
		 * Update the emoji of a collective
		 *
		 * @param {string} emoji Emoji
		 */
		updateEmoji(emoji) {
			this.load('updateCollectiveEmoji')
			const collective = { ...this.collective }
			collective.emoji = emoji
			this.$store.dispatch(UPDATE_COLLECTIVE, collective).then(() => {
				showSuccess(t('collectives', 'Emoji updated'))
				this.done('updateCollectiveEmoji')
			}).catch((error) => {
				showError('Could not update emoji for the collective')
				this.done('updateCollectiveEmoji')
				throw error
			})
		},

		/**
		 * Rename circle and reload collective
		 */
		async renameCollective() {
			// Ignore rename to same name
			if (this.newCollectiveName === this.collective.name) {
				return
			}

			this.load('renameCollective')

			// If currentCollective is renamed, we need to update the router path later
			const redirect = this.collectiveParam === this.collective.name

			// Wait for circle rename (also patches store with updated collective and pages)
			const collective = { ...this.collective }
			collective.name = this.newCollectiveName
			await this.$store.dispatch(RENAME_CIRCLE, collective).then(() => {
				showSuccess('Collective renamed')
			}).catch((error) => {
				showError('Could not rename the collective')
				this.done('renameCollective')
				throw error
			})

			// Name might have changed (due to circle name conflicts), update input field
			this.newCollectiveName = this.collective.name

			// Push new router path if currentCollective was renamed
			if (redirect) {
				this.$router.push(
					'/' + encodeURIComponent(this.newCollectiveName)
					+ (this.pageParam ? '/' + this.pageParam : '')
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
			this.$store.dispatch(TRASH_COLLECTIVE, this.collective)
				.catch(displayError('Could not move the collective to trash'))
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .modal-wrapper.modal-wrapper--normal .modal-container {
	display: flex;
}

.app-settings-section {
	margin-bottom: 45px;
}

button.emoji {
	font-size: 15px;
	padding-left: 19px;
	background-color: transparent;
	border: none;
}

.collective-name {
	order: 1;
	display: flex;
	height: 44px;

	form {
		display: flex;
		flex-grow: 1;

		input[type='text'] {
			flex-grow: 1;
		}
	}
}

.subsection-header {
	margin-bottom: 12px;
	&__second {
		margin-top: 12px;
	}
}
</style>
