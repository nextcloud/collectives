<template>
	<NcAppNavigationItem :key="collective.circleId"
		:title="collective.name"
		:class="{active: isActive(collective)}"
		:to="`/${encodeURIComponent(collective.name)}`"
		:force-menu="true"
		:force-display-actions="isMobile"
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
			<NcActionLink v-if="showManageMembers"
				:href="circleLink">
				<template #icon>
					<CirclesIcon :size="16" />
				</template>
				{{ t('collectives', 'Manage members') }}
			</NcActionLink>
			<NcActionSeparator v-if="showManageMembers" />
			<NcActionButton v-if="collectiveCanShare(collective)"
				v-show="!isShared"
				:icon="shareIcon"
				:close-after-click="false"
				@click="share(collective)">
				{{ t('collectives', 'Share link') }}
			</NcActionButton>
			<NcActionButton v-if="!isPublic"
				v-show="isShared"
				:icon="copyLinkIcon"
				:close-after-click="false"
				@click.stop.prevent="copyShare(collective)">
				{{ copyButtonText }}
			</NcActionButton>
			<NcActionCheckbox v-if="!isPublic"
				v-show="isShared && collectiveCanEdit(collective)"
				id="shareEditable"
				:disabled="loading('shareEditable')"
				:checked.sync="shareEditable">
				{{ t('collectives', 'Allow editing') }}
			</NcActionCheckbox>
			<NcActionButton v-if="!isPublic"
				v-show="isShared"
				:icon="unshareIcon"
				:close-after-click="false"
				@click="unshare(collective)">
				{{ t('collectives', 'Unshare') }}
			</NcActionButton>
			<NcActionSeparator v-if="collectiveCanShare(collective)" />
			<NcActionLink :close-after-click="true"
				:href="printLink"
				target="_blank">
				{{ t('collectives', 'Export or print') }}
				<template #icon>
					<DownloadIcon :size="16" />
				</template>
			</NcActionLink>
			<NcActionButton v-if="isCollectiveAdmin(collective)"
				icon="icon-settings"
				:close-after-click="true"
				@click="toggleCollectiveSettings(collective)">
				{{ t('collectives', 'Settings') }}
			</NcActionButton>
		</template>
		<template #extra>
			<CollectiveSettings :open.sync="showCollectiveSettings"
				:collective="collective" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import { NcActionButton, NcActionCheckbox, NcActionLink, NcActionSeparator, NcAppNavigationItem } from '@nextcloud/vue'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { generateUrl } from '@nextcloud/router'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import { SHARE_COLLECTIVE, UPDATE_SHARE_COLLECTIVE, UNSHARE_COLLECTIVE } from '../../store/actions.js'
import displayError from '../../util/displayError.js'
import CopyToClipboardMixin from '../../mixins/CopyToClipboardMixin.js'
import CirclesIcon from '../Icon/CirclesIcon.vue'
import CollectiveSettings from './CollectiveSettings.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'

export default {
	name: 'CollectiveListItem',

	components: {
		NcActionButton,
		NcActionCheckbox,
		NcActionLink,
		NcActionSeparator,
		NcAppNavigationItem,
		CirclesIcon,
		CollectiveSettings,
		CollectivesIcon,
		DownloadIcon,
	},

	mixins: [
		CopyToClipboardMixin,
		isMobile,
	],

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
