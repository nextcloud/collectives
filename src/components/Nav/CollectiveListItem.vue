<template>
	<AppNavigationItem :key="collective.circleId"
		:title="collective.name"
		:class="{active: isActive(collective)}"
		:to="`/${encodeURIComponent(collective.name)}`"
		:force-menu="true"
		class="collectives_list_item">
		<template #icon>
			<template v-if="collective.emoji">
				{{ collective.emoji }}
			</template>
			<template v-else>
				<CollectivesIcon :size="20" />
			</template>
		</template>
		<template #actions>
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
				:icon="copyLinkIcon"
				:close-after-click="false"
				@click.stop.prevent="copyShare(collective)">
				{{ copyButtonText }}
			</ActionButton>
			<ActionCheckbox v-if="!isPublic"
				v-show="isShared && collectiveCanEdit(collective)"
				id="shareEditable"
				:disabled="loading('shareEditable')"
				:checked.sync="shareEditable">
				{{ t('collectives', 'Allow editing') }}
			</ActionCheckbox>
			<ActionButton v-if="!isPublic"
				v-show="isShared"
				:icon="unshareIcon"
				:close-after-click="false"
				@click="unshare(collective)">
				{{ t('collectives', 'Unshare') }}
			</ActionButton>
			<ActionSeparator v-if="collectiveCanShare(collective)" />
			<ActionLink :close-after-click="true"
				:href="printLink"
				target="_blank">
				{{ t('collectives', 'Export or print') }}
				<DownloadIcon slot="icon"
					:size="16"
					decorative />
			</ActionLink>
			<ActionButton v-if="isCollectiveAdmin(collective)"
				icon="icon-settings"
				:close-after-click="true"
				@click="toggleCollectiveSettings(collective)">
				{{ t('collectives', 'Settings') }}
			</ActionButton>
		</template>
		<template #extra>
			<CollectiveSettings :open.sync="showCollectiveSettings"
				:collective="collective" />
		</template>
	</AppNavigationItem>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import { SHARE_COLLECTIVE, UPDATE_SHARE_COLLECTIVE, UNSHARE_COLLECTIVE } from '../../store/actions'
import displayError from '../../util/displayError'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import { generateUrl } from '@nextcloud/router'
import CopyToClipboardMixin from '../../mixins/CopyToClipboardMixin'
import DownloadIcon from 'vue-material-design-icons/Download'
import CirclesIcon from '../Icon/CirclesIcon'
import CollectiveSettings from './CollectiveSettings'
import CollectivesIcon from '../Icon/CollectivesIcon'

export default {
	name: 'CollectiveListItem',

	components: {
		ActionButton,
		ActionCheckbox,
		ActionLink,
		ActionSeparator,
		AppNavigationItem,
		CirclesIcon,
		CollectiveSettings,
		CollectivesIcon,
		DownloadIcon,
	},

	mixins: [CopyToClipboardMixin],

	props: {
		collective: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			showCollectiveSettings: false,
			shareEditable: this.collective.shareEditable,
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'collectiveParam',
			'collectives',
			'collectiveShareUrl',
			'collectiveCanEdit',
			'collectiveCanShare',
			'isCollectiveAdmin',
			'loading',
			'shareTokenParam',
		]),

		isContactsInstalled() {
			return 'contacts' in this.OC.appswebroots
		},

		showManageMembers() {
			return this.isCollectiveAdmin(this.collective) && this.isContactsInstalled
		},

		isShared() {
			return !!this.collective.shareToken
		},

		circleLink() {
			return generateUrl('/apps/contacts/direct/circle/' + this.collective.circleId)
		},

		printLink() {
			return this.isPublic
				? generateUrl(`/apps/collectives/p/${this.shareTokenParam}/print/${this.collective.name}`)
				: generateUrl(`/apps/collectives/_/print/${this.collective.name}`)
		},

		icon() {
			return this.collective.emoji ? '' : 'icon-collectives'
		},

		shareIcon() {
			return this.loading('share') ? 'icon-loading-small' : 'icon-public'
		},

		unshareIcon() {
			return this.loading('unshare') ? 'icon-loading-small' : 'icon-public'
		},

		copyButtonText() {
			if (this.copied) {
				return this.copySuccess
					? t('collectives', 'Copied')
					: t('collectives', 'Cannot copy')
			}
			return t('collectives', 'Copy share link')
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

		isActive(collective) {
			return this.collectiveParam === collective.name
		},

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

		toggleCollectiveSettings() {
			this.showCollectiveSettings = true
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .app-navigation-entry-icon {
	font-size: 20px;
}
</style>
