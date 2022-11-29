<template>
	<div>
		<ActionLink v-if="showManageMembers"
			:href="circleLink">
			<template #icon>
				<CirclesIcon :size="16" />
			</template>
			{{ t('collectives', 'Manage members') }}
		</ActionLink>
		<ActionSeparator v-if="showManageMembers" />
		<ActionButton v-if="collectiveCanShare(collective)"
			v-show="!isShared"
			:icon="shareIcon"
			:close-after-click="false"
			@click="share(collective)">
			{{ t('collectives', 'Share link') }}
		</ActionButton>
		<ActionButton v-if="!isPublic"
			v-show="isShared"
			:close-after-click="false"
			@click.stop.prevent="copyShare(collective)">
			<template #icon>
				<CheckIcon v-if="copySuccess" :size="16" />
				<LoadingIcon v-else-if="copyLoading" :size="16" />
				<ContentPasteIcon v-else :size="16" />
			</template>
			{{ copyButtonText }}
		</ActionButton>
		<ActionCheckbox v-if="!isPublic"
			v-show="isShared && collectiveCanEdit(collective)"
			id="shareEditable"
			:disabled="loading('shareEditable')"
			:checked.sync="shareEditable">
			{{ t('collectives', 'Allow editing in share') }}
		</ActionCheckbox>
		<ActionButton v-if="!isPublic"
			v-show="isShared"
			:icon="unshareIcon"
			:close-after-click="false"
			@click="unshare(collective)">
			{{ t('collectives', 'Unshare') }}
		</ActionButton>
		<ActionSeparator v-if="collectiveCanShare(collective) && !isPublic" />
		<ActionLink :close-after-click="true"
			:href="printLink"
			target="_blank">
			{{ t('collectives', 'Export or print') }}
			<template #icon>
				<DownloadIcon :size="16" />
			</template>
		</ActionLink>
		<ActionButton v-if="isCollectiveAdmin(collective)"
			icon="icon-settings"
			:close-after-click="true"
			@click="openCollectiveSettings()">
			{{ t('collectives', 'Settings') }}
		</ActionButton>
	</div>
</template>

<script>
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { ActionButton, ActionCheckbox, ActionLink, ActionSeparator, LoadingIcon } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import ContentPasteIcon from 'vue-material-design-icons/ContentPaste.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import { SHARE_COLLECTIVE, UPDATE_SHARE_COLLECTIVE, UNSHARE_COLLECTIVE } from '../../store/actions.js'
import displayError from '../../util/displayError.js'
import CopyToClipboardMixin from '../../mixins/CopyToClipboardMixin.js'
import CirclesIcon from '../Icon/CirclesIcon.vue'

export default {
	name: 'CollectiveActions',

	components: {
		ActionButton,
		ActionCheckbox,
		ActionLink,
		ActionSeparator,
		LoadingIcon,
		CirclesIcon,
		ContentPasteIcon,
		CheckIcon,
		DownloadIcon,
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

		shareIcon() {
			return this.loading('share') ? 'icon-loading-small' : 'icon-public'
		},

		copyButtonText() {
			if (this.copied) {
				return this.copySuccess
					? t('collectives', 'Copied')
					: t('collectives', 'Cannot copy')
			}
			return t('collectives', 'Copy share link')
		},

		unshareIcon() {
			return this.loading('unshare') ? 'icon-loading-small' : 'icon-public'
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
					.catch(displayError(t('collectives', 'Could not change the collective share editing permissions')))
			}
		},
	},

	methods: {
		...mapActions({
			dispatchShareCollective: SHARE_COLLECTIVE,
			dispatchUnshareCollective: UNSHARE_COLLECTIVE,
			dispatchUpdateShareCollective: UPDATE_SHARE_COLLECTIVE,
		}),

		...mapMutations([
			'setSettingsCollectiveId',
		]),

		share(collective) {
			return this.dispatchShareCollective(collective)
				.catch(displayError(t('collectives', 'Could not share the collective')))
		},

		unshare(collective) {
			this.shareEditable = undefined
			return this.dispatchUnshareCollective(collective)
				.catch(displayError(t('collectives', 'Could not unshare the collective')))
		},

		copyShare(collective) {
			this.copyToClipboard(window.location.origin + this.collectiveShareUrl(collective))
		},

		openCollectiveSettings() {
			this.setSettingsCollectiveId(this.collective.id)
		},
	},
}
</script>

<style scoped>

</style>
