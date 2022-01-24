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
						class="icon-rename"
						:class="{ 'icon-loading-small': loading }"
						:disabled="!isCollectiveOwner(collective)">
				</form>
			</div>
		</AppSettingsSection>

		<AppSettingsSection :title="t('collectives', 'Permissions')">
			<div class="permissions-header">
				{{ t('collectives', 'Allow editing for') }}
			</div>

			<div class="permissions-input-edit">
				<input id="edit_admins"
					v-model="editPermissions"
					type="radio"
					class="radio"
					:value="memberLevels.LEVEL_ADMIN">
				<label :class="{ primary: parseInt(editPermissions) === memberLevels.LEVEL_ADMIN }" for="edit_admins">
					{{ t('collectives', 'Admins only') }}
				</label>
			</div>
			<div>
				<input id="edit_moderators"
					v-model="editPermissions"
					type="radio"
					class="radio"
					:value="memberLevels.LEVEL_MODERATOR">
				<label :class="{ primary: parseInt(editPermissions) === memberLevels.LEVEL_MODERATOR }" for="edit_moderators">
					{{ t('collectives', 'Admins and moderaters') }}
				</label>
			</div>
			<div>
				<input id="edit_members"
					v-model="editPermissions"
					type="radio"
					class="radio"
					:value="memberLevels.LEVEL_MEMBER">
				<label :class="{ primary: parseInt(editPermissions) === memberLevels.LEVEL_MEMBER }" for="edit_members">
					{{ t('collectives', 'All members') }}
				</label>
			</div>

			<div class="permissions-header permissions-header__second">
				{{ t('collectives', 'Allow sharing for') }}
			</div>

			<div class="permissions-input-share">
				<input id="share_admins"
					v-model="sharePermissions"
					type="radio"
					class="radio"
					:value="memberLevels.LEVEL_ADMIN">
				<label :class="{ primary: parseInt(sharePermissions) === memberLevels.LEVEL_ADMIN }" for="share_admins">
					{{ t('collectives', 'Admins only') }}
				</label>
			</div>
			<div>
				<input id="share_moderators"
					v-model="sharePermissions"
					type="radio"
					class="radio"
					:value="memberLevels.LEVEL_MODERATOR">
				<label :class="{ primary: parseInt(sharePermissions) === memberLevels.LEVEL_MODERATOR }" for="share_moderators">
					{{ t('collectives', 'Admins and moderaters') }}
				</label>
			</div>
			<div>
				<input id="share_members"
					v-model="sharePermissions"
					type="radio"
					class="radio"
					:value="memberLevels.LEVEL_MEMBER">
				<label :class="{ primary: parseInt(sharePermissions) === memberLevels.LEVEL_MEMBER }" for="share_members">
					{{ t('collectives', 'All members') }}
				</label>
			</div>
		</AppSettingsSection>

		<AppSettingsSection :title="t('collectives', 'Members')">
			<div>
				{{ t('collectives', 'Members can be managed in the Contacts app.') }}
			</div>
			<div>
				<!-- TODO: Use secondary button from @nextcloud/vue 5.0 once it's there -->
				<button v-tooltip="membersDisabledTooltip"
					class="button"
					:disabled="!isContactsInstalled"
					@click="openCircleLink">
					{{ t('collectives', 'Open Contacts') }}
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
import { mapGetters, mapState } from 'vuex'
import { showError, showSuccess } from '@nextcloud/dialogs'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
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
			loading: false,
			showSettings: false,
			editPermissions: this.collective.editPermissionLevel,
			sharePermissions: this.collective.sharePermissionLevel,
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
			this.$store.dispatch(UPDATE_COLLECTIVE_EDIT_PERMISSIONS, { id: this.collective.id, level: parseInt(val) }).then(() => {
				showSuccess(t('collectives', 'Editing permissions updated'))
			}).catch((error) => {
				showError('Could not update editing permissions')
				this.editPermissions = this.collective.editPermissionLevel
				throw error
			})
		},
		sharePermissions(val) {
			this.$store.dispatch(UPDATE_COLLECTIVE_SHARE_PERMISSIONS, { id: this.collective.id, level: parseInt(val) }).then(() => {
				showSuccess(t('collectives', 'Sharing permissions updated'))
			}).catch((error) => {
				showError('Could not update sharing permissions')
				this.sharePermissions = this.collective.sharePermissionLevel
				throw error
			})
		},
	},

	methods: {
		/**
		 * Update the emoji of a collective
		 *
		 * @param {string} emoji Emoji
		 */
		updateEmoji(emoji) {
			const collective = this.collective
			collective.emoji = emoji
			this.$store.dispatch(UPDATE_COLLECTIVE, collective)
				.catch(displayError('Could not update emoji for the collective'))
		},

		/**
		 * Rename circle and reload collective
		 */
		async renameCollective() {
			// Ignore rename to same name
			if (this.newCollectiveName === this.collective.name) {
				return
			}

			this.loading = true

			// If currentCollective is renamed, we need to update the router path later
			const redirect = this.collectiveParam === this.collective.name

			// Wait for circle rename (also patches store with updated collective and pages)
			const collective = { ...this.collective }
			collective.name = this.newCollectiveName
			await this.$store.dispatch(RENAME_CIRCLE, collective)

			// Name might have changed (due to circle name conflicts), update input field
			this.newCollectiveName = this.collective.name

			// Push new router path if currentCollective was renamed
			if (redirect) {
				this.$router.push(
					'/' + encodeURIComponent(this.newCollectiveName)
					+ (this.pageParam ? '/' + this.pageParam : '')
				)
			}

			this.loading = false
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

			// Copied from `core/cs/inputs.scss` for `icon-confirm`
			+ .icon-rename {
				margin-left: -8px !important;
				border-left-color: transparent !important;
				border-radius: 0 var(--border-radius) var(--border-radius) 0 !important;
				background-clip: padding-box;
				/* Avoid background under border */
				background-color: var(--color-main-background) !important;
				opacity: 1;
				height: 34px;
				width: 34px;
				padding: 7px 6px;
				cursor: pointer;
				margin-right: 0;
				&:disabled {
					cursor: default;
				}
			}

			/* only show rename borders if input is not focussed */
			&:not(:active):not(:hover):not(:focus) {
				&:invalid {
					+ .icon-rename {
						border-color: var(--color-error);
					}
				}
				+ .icon-rename {
					&:active,
					&:hover,
					&:focus {
						border-color: var(--color-primary-element) !important;
						border-radius: var(--border-radius) !important;
						&:disabled {
							border-color: var(--color-background-dark) !important;
						}
					}
				}
			}
			&:active,
			&:hover,
			&:focus {
				+ .icon-rename {
					border-color: var(--color-primary-element) !important;
					border-left-color: transparent !important;
					/* above previous input */
					z-index: 2;
					&:disabled {
						border-color: var(--color-background-darker) !important;
					}
				}
			}
		}
	}
}

.permissions-header {
	margin-bottom: 12px;
	&__second {
		margin-top: 12px;
	}
}
</style>
