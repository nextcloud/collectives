<template>
	<AppSettingsDialog :open.sync="showSettings"
		:aria-label="t('collectives', 'Collective settings')"
		:show-navigation="true">
		<AppSettingsSection :title="t('collectives', 'Name and emoji')">
			<div class="collective-name">
				<EmojiPicker :show-preview="true"
					@select="updateEmoji">
					<Button type="tertiary"
						:aria-label="t('collectives', 'Select emoji for collective')"
						:title="emojiTitle"
						:class="{'loading': loading('updateCollectiveEmoji')}"
						class="button-emoji"
						@click.prevent>
						{{ collective.emoji }}
						<template v-if="!collective.emoji" #icon>
							<EmoticonOutline :size="20" />
						</template>
					</Button>
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
						:aria-label="t('collectives', 'Save new collective name')"
						class="icon-confirm"
						:class="{ 'icon-loading-small': loading('renameCollective') }"
						:disabled="!isCollectiveOwner(collective)">
				</form>
			</div>
		</AppSettingsSection>

		<AppSettingsSection :title="t('collectives', 'Default page order')">
			<div class="page-order">
				<CheckboxRadioSwitch :checked.sync="pageOrder"
					:value="String(pageOrders.byTimestamp)"
					:loading="loading('updateCollectivePageOrder_' + String(pageOrders.byTimestamp))"
					name="page_order_timestamp"
					type="radio">
					{{ t('collectives', 'Sort recently changed first') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch :checked.sync="pageOrder"
					:value="String(pageOrders.byTitle)"
					:loading="loading('updateCollectivePageOrder_' + String(pageOrders.byTitle))"
					name="page_order_title"
					type="radio">
					{{ t('collectives', 'Sort by title') }}
				</CheckboxRadioSwitch>
			</div>
		</AppSettingsSection>

		<AppSettingsSection :title="t('collectives', 'Permissions')">
			<div class="subsection-header">
				{{ t('collectives', 'Allow editing for') }}
			</div>

			<div class="permissions-input-edit">
				<CheckboxRadioSwitch :checked.sync="editPermissions"
					:value="String(memberLevels.LEVEL_ADMIN)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_ADMIN))"
					name="edit_admins"
					type="radio">
					{{ t('collectives', 'Admins only') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch :checked.sync="editPermissions"
					:value="String(memberLevels.LEVEL_MODERATOR)"
					:loading="loading('updateCollectiveEditPermissions_' + String(memberLevels.LEVEL_MODERATOR))"
					name="edit_moderators"
					type="radio">
					{{ t('collectives', 'Admins and moderaters') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch :checked.sync="editPermissions"
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
				<CheckboxRadioSwitch :checked.sync="sharePermissions"
					:value="String(memberLevels.LEVEL_ADMIN)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_ADMIN))"
					name="share_admins"
					type="radio">
					{{ t('collectives', 'Admins only') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch :checked.sync="sharePermissions"
					:value="String(memberLevels.LEVEL_MODERATOR)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_MODERATOR))"
					name="share_moderators"
					type="radio">
					{{ t('collectives', 'Admins and moderaters') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch :checked.sync="sharePermissions"
					:value="String(memberLevels.LEVEL_MEMBER)"
					:loading="loading('updateCollectiveSharePermissions_' + String(memberLevels.LEVEL_MEMBER))"
					name="share_members"
					type="radio">
					{{ t('collectives', 'All members') }}
				</CheckboxRadioSwitch>
			</div>
		</AppSettingsSection>

		<AppSettingsSection :title="t('collectives', 'Members')">
			<div class="section-description">
				{{ t('collectives', 'Members can be managed via the connected circle in the Contacts app.') }}
			</div>
			<div>
				<Button v-tooltip="membersDisabledTooltip"
					:aria-label="t('collectives', 'Open circle in Contacts')"
					:disabled="!isContactsInstalled"
					@click="openCircleLink">
					{{ t('collectives', 'Open circle in Contacts') }}
				</Button>
			</div>
		</AppSettingsSection>

		<AppSettingsSection :title="t('collectives', 'Danger zone')">
			<div>
				<Button type="error"
					:aria-label="t('collectives', 'Delete collective')"
					@click="trashCollective()">
					{{ t('collectives', 'Delete collective') }}
				</Button>
			</div>
		</AppSettingsSection>
	</AppSettingsDialog>
</template>

<script>
import { memberLevels } from '../../constants.js'
import { pageOrders, pageOrdersByNumber } from '../../util/sortOrders.js'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { showError, showSuccess } from '@nextcloud/dialogs'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection'
import Button from '@nextcloud/vue/dist/Components/Button'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline'
import { generateUrl } from '@nextcloud/router'
import {
	RENAME_CIRCLE,
	UPDATE_COLLECTIVE,
	TRASH_COLLECTIVE,
	UPDATE_COLLECTIVE_EDIT_PERMISSIONS,
	UPDATE_COLLECTIVE_SHARE_PERMISSIONS,
} from '../../store/actions.js'
import displayError from '../../util/displayError.js'

export default {
	name: 'CollectiveSettings',

	components: {
		AppSettingsDialog,
		AppSettingsSection,
		Button,
		CheckboxRadioSwitch,
		EmojiPicker,
		EmoticonOutline,
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
			pageOrders,
			newCollectiveName: this.collective.name,
			showSettings: false,
			editPermissions: String(this.collective.editPermissionLevel),
			sharePermissions: String(this.collective.sharePermissionLevel),
			pageOrder: String(this.collective.pageOrder),
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
			this.dispatchUpdateCollectiveEditPermissions({ id: this.collective.id, level: parseInt(permission) }).then(() => {
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
			this.dispatchUpdateCollectiveSharePermissions({ id: this.collective.id, level: parseInt(permission) }).then(() => {
				showSuccess(t('collectives', 'Sharing permissions updated'))
				this.done('updateCollectiveSharePermissions_' + permission)
			}).catch((error) => {
				this.sharePermissions = String(this.collective.sharePermissionLevel)
				this.done('updateCollectiveSharePermissions_' + permission)
				showError('Could not update sharing permissions')
				throw error
			})
		},
		pageOrder(val, oldVal) {
			const pageOrder = String(val)
			this.load('updateCollectivePageOrder_' + pageOrder)
			const collective = { id: this.collective.id }
			collective.pageOrder = parseInt(pageOrder)
			this.dispatchUpdateCollective(collective).then(() => {
				this.setPageOrder(pageOrdersByNumber[pageOrder])
				showSuccess(t('collectives', 'Default page order updated'))
				this.done('updateCollectivePageOrder_' + pageOrder)
			}).catch((error) => {
				showError('Could not update default page order')
				this.pageOrder = String(this.collective.pageOrder)
				this.done('updateCollectivePageOrder_' + pageOrder)
				throw error
			})
		},
	},

	methods: {
		...mapMutations(['load', 'done', 'setPageOrder']),

		...mapActions({
			dispatchRenameCircle: RENAME_CIRCLE,
			dispatchUpdateCollective: UPDATE_COLLECTIVE,
			dispatchTrashCollective: TRASH_COLLECTIVE,
			dispatchUpdateCollectiveEditPermissions: UPDATE_COLLECTIVE_EDIT_PERMISSIONS,
			dispatchUpdateCollectiveSharePermissions: UPDATE_COLLECTIVE_SHARE_PERMISSIONS,
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
			await this.dispatchRenameCircle(collective).then(() => {
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
			this.dispatchTrashCollective(this.collective)
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

.button-emoji {
	font-size: 20px;
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
