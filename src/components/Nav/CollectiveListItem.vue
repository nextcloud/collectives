<template>
	<AppNavigationItem :key="collective.circleId"
		:title="collective.name"
		:class="{active: isActive(collective)}"
		:to="`/${encodeURIComponent(collective.name)}`"
		:icon="icon"
		:force-menu="true"
		class="collectives_list_item">
		<template v-if="collective.emoji" #icon>
			{{ collective.emoji }}
		</template>
		<template #actions>
			<ActionLink v-if="showManageMembers"
				:href="circleLink"
				icon="icon-circles">
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
			<ActionButton :close-after-click="true"
				@click="print">
				{{ t('collectives', 'Print') }}
				<PrinterIcon slot="icon"
					:size="16"
					decorative />
			</ActionButton>
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
import { mapGetters, mapMutations } from 'vuex'
import { SHARE_COLLECTIVE, UPDATE_SHARE_COLLECTIVE, UNSHARE_COLLECTIVE } from '../../store/actions'
import displayError from '../../util/displayError'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import { generateUrl } from '@nextcloud/router'
import CopyToClipboardMixin from '../../mixins/CopyToClipboardMixin'
import PrinterIcon from 'vue-material-design-icons/Printer'
import CollectiveSettings from './CollectiveSettings'

export default {
	name: 'CollectiveListItem',

	components: {
		ActionButton,
		ActionCheckbox,
		ActionLink,
		ActionSeparator,
		AppNavigationItem,
		CollectiveSettings,
		PrinterIcon,
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
				return this.$store.dispatch(UPDATE_SHARE_COLLECTIVE, collective)
					.catch(displayError(t('collectives', 'Could not change the collective share editing permissions')))
			}
		},
	},

	methods: {
		...mapMutations(['show']),

		isActive(collective) {
			return this.collectiveParam === collective.name
		},

		showSubpagesAndPrint() {
			this.show('subpages')
			this.show('print')
		},

		print() {
			this.$router.push(`/${encodeURIComponent(this.collective.name)}`)
				.catch((err) => {
					// Navigation is aborted since navigating to same route, but we still want to print
					if (err.name !== 'NavigationDuplicated') {
						throw err
					}
				}).then(() => this.showSubpagesAndPrint())
		},

		share(collective) {
			return this.$store.dispatch(SHARE_COLLECTIVE, collective)
				.catch(displayError(t('collectives', 'Could not share the collective')))
		},

		unshare(collective) {
			this.shareEditable = undefined
			return this.$store.dispatch(UNSHARE_COLLECTIVE, collective)
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

	&.icon-collectives {
		background-size: 20px 20px;
	}
}
</style>
