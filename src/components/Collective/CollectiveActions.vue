<template>
	<div>
		<NcActionLink v-if="showManageMembers"
			:href="circleLink">
			<template #icon>
				<CirclesIcon :size="20" />
			</template>
			{{ t('collectives', 'Manage members') }}
		</NcActionLink>
		<NcActionSeparator v-if="showManageMembers" />
		<NcActionButton v-if="collectiveCanShare(collective)"
			v-show="!isShared"
			:close-after-click="false"
			@click="share(collective)">
			{{ t('collectives', 'Share link') }}
			<template #icon>
				<NcLoadingIcon v-if="loading('share')" :size="20" />
				<LinkVariantIcon v-else :size="20" />
			</template>
		</NcActionButton>
		<NcActionButton v-if="!isPublic"
			v-show="isShared"
			:close-after-click="false"
			@click.stop.prevent="copyShare(collective)">
			<template #icon>
				<CheckIcon v-if="copySuccess" :size="20" />
				<NcLoadingIcon v-else-if="copyLoading" :size="20" />
				<ContentPasteIcon v-else :size="20" />
			</template>
			{{ copyButtonText }}
		</NcActionButton>
		<NcActionCheckbox v-if="!isPublic"
			v-show="isShared && collectiveCanEdit(collective)"
			id="shareEditable"
			:disabled="loading('shareEditable')"
			:checked.sync="shareEditable">
			{{ t('collectives', 'Allow editing in share') }}
		</NcActionCheckbox>
		<NcActionButton v-if="!isPublic"
			v-show="isShared"
			:close-after-click="false"
			@click="unshare(collective)">
			<template #icon>
				<NcLoadingIcon v-if="loading('unshare')" :size="20" />
				<LinkVariantIcon v-else :size="20" />
			</template>
			{{ t('collectives', 'Unshare') }}
		</NcActionButton>
		<NcActionSeparator v-if="collectiveCanShare(collective) && !isPublic" />
		<NcActionLink :close-after-click="true"
			:href="printLink"
			target="_blank">
			{{ t('collectives', 'Export or print') }}
			<template #icon>
				<DownloadIcon :size="20" />
			</template>
		</NcActionLink>
		<NcActionButton v-if="isCollectiveAdmin(collective)"
			:close-after-click="true"
			@click="openCollectiveSettings()">
			<template #icon>
				<CogIcon :size="20" />
			</template>
			{{ t('collectives', 'Settings') }}
		</NcActionButton>
		<NcActionButton v-if="!isCollectiveAdmin(collective)"
			:close-after-click="true"
			@click="leaveCollectiveWithUndo(collective)">
			{{ t('collectives', 'Leave collective') }}
			<template #icon>
				<LogoutIcon :size="20" />
			</template>
		</NcActionButton>
	</div>
</template>

<script>
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { NcActionButton, NcActionCheckbox, NcActionLink, NcActionSeparator, NcLoadingIcon } from '@nextcloud/vue'
import { showError, showUndo } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CirclesIcon from '../Icon/CirclesIcon.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import ContentPasteIcon from 'vue-material-design-icons/ContentPaste.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import LogoutIcon from 'vue-material-design-icons/Logout.vue'
import {
	LEAVE_CIRCLE,
	MARK_COLLECTIVE_DELETED,
	SHARE_COLLECTIVE,
	UNMARK_COLLECTIVE_DELETED,
	UPDATE_SHARE_COLLECTIVE,
	UNSHARE_COLLECTIVE,
} from '../../store/actions.js'
import displayError from '../../util/displayError.js'
import CopyToClipboardMixin from '../../mixins/CopyToClipboardMixin.js'

export default {
	name: 'CollectiveActions',

	components: {
		CirclesIcon,
		CheckIcon,
		CogIcon,
		ContentPasteIcon,
		DownloadIcon,
		LinkVariantIcon,
		LogoutIcon,
		NcActionButton,
		NcActionCheckbox,
		NcActionLink,
		NcActionSeparator,
		NcLoadingIcon,
	},

	mixins: [
		CopyToClipboardMixin,
	],

	props: {
		collective: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			leaveTimeout: null,
			shareEditable: this.collective.shareEditable,
		}
	},

	computed: {
		...mapGetters([
			'collectiveCanEdit',
			'collectiveCanShare',
			'collectiveParam',
			'collectiveShareUrl',
			'isCollectiveAdmin',
			'isPublic',
			'loading',
		]),

		isContactsInstalled() {
			return 'contacts' in this.OC.appswebroots
		},

		showManageMembers() {
			return this.isCollectiveAdmin(this.collective) && this.isContactsInstalled
		},

		circleLink() {
			return generateUrl('/apps/contacts/direct/circle/' + this.collective.circleId)
		},

		isShared() {
			return !!this.collective.shareToken
		},

		copyButtonText() {
			if (this.copied) {
				return this.copySuccess
					? t('collectives', 'Copied')
					: t('collectives', 'Cannot copy')
			}
			return t('collectives', 'Copy share link')
		},

		printLink() {
			return this.isPublic
				? generateUrl(`/apps/collectives/p/${this.shareTokenParam}/print/${this.collective.name}`)
				: generateUrl(`/apps/collectives/_/print/${this.collective.name}`)
		},
	},

	watch: {
		shareEditable(val) {
			if (val !== undefined) {
				const collective = { ...this.collective }
				collective.shareEditable = val
				return this.dispatchUpdateShareCollective(collective)
					.catch(displayError('Could not change the collective share editing permissions'))
			}
		},
	},

	methods: {
		...mapActions({
			dispatchLeaveCircle: LEAVE_CIRCLE,
			dispatchShareCollective: SHARE_COLLECTIVE,
			dispatchUnshareCollective: UNSHARE_COLLECTIVE,
			dispatchUpdateShareCollective: UPDATE_SHARE_COLLECTIVE,
			dispatchMarkCollectiveDeleted: MARK_COLLECTIVE_DELETED,
			dispatchUnmarkCollectiveDeleted: UNMARK_COLLECTIVE_DELETED,
		}),

		...mapMutations([
			'setSettingsCollectiveId',
		]),

		share(collective) {
			return this.dispatchShareCollective(collective)
				.catch(displayError('Could not share the collective'))
		},

		unshare(collective) {
			this.shareEditable = undefined
			return this.dispatchUnshareCollective(collective)
				.catch(displayError('Could not unshare the collective'))
		},

		copyShare(collective) {
			this.copyToClipboard(window.location.origin + this.collectiveShareUrl(collective))
		},

		openCollectiveSettings() {
			this.setSettingsCollectiveId(this.collective.id)
		},

		leaveCollectiveWithUndo(collective) {
			showUndo(
				t('collectives', 'Left collective {name}', { name: collective.name }),
				() => {
					clearTimeout(this.leaveTimeout)
					this.leaveTimeout = null
					this.dispatchUnmarkCollectiveDeleted(collective)
				}
			)

			this.dispatchMarkCollectiveDeleted(collective)

			this.leaveTimeout = setTimeout(() => {
				this.dispatchLeaveCircle(collective).catch((e) => {
					console.error('Failed to leave collective', e)
					let errorMessage = ''
					if (e.response?.data?.ocs?.meta?.message) {
						errorMessage = e.response.data.ocs.meta.message
					}
					showError(t('collectives', 'Could not leave the collective. {errorMessage}', { errorMessage }))
					this.dispatchUnmarkCollectiveDeleted(collective)
				})
			}, 10000)
		},
	},
}
</script>

<style scoped>

</style>
