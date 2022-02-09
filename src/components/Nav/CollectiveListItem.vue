<template>
	<AppNavigationItem
		:key="collective.circleId"
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
			<ActionButton v-if="isCollectiveSharable(collective)"
				v-show="!collective.shareToken"
				:icon="shareIcon"
				:close-after-click="false"
				@click="share(collective)">
				{{ t('collectives', 'Share link') }}
			</ActionButton>
			<ActionButton v-if="!isPublic"
				v-show="collective.shareToken"
				:icon="copyLinkIcon"
				:close-after-click="false"
				@click.stop.prevent="copyShare(collective)">
				{{ copyButtonText }}
			</ActionButton>
			<ActionButton v-if="!isPublic"
				v-show="collective.shareToken"
				:icon="unshareIcon"
				:close-after-click="false"
				@click="unshare(collective)">
				{{ t('collectives', 'Unshare') }}
			</ActionButton>
			<ActionButton :close-after-click="true"
				@click="print">
				{{ t('collectives', 'Print') }}
				<PrinterIcon slot="icon"
					:size="16"
					decorative />
			</ActionButton>
			<ActionLink v-if="isCollectiveAdmin(collective) && isContactsInstalled"
				:href="circleLink"
				icon="icon-circles">
				{{ t('collectives', 'Manage members') }}
			</ActionLink>
			<ActionButton v-if="isCollectiveAdmin(collective)"
				icon="icon-settings"
				:close-after-click="true"
				@click="toggleCollectiveSettings(collective)">
				{{ t('collectives', 'Settings') }}
			</ActionButton>
		</template>
		<template #extra>
			<CollectiveSettings
				:open.sync="showCollectiveSettings"
				:collective="collective" />
		</template>
	</AppNavigationItem>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import { SHARE_COLLECTIVE, UNSHARE_COLLECTIVE } from '../../store/actions'
import displayError from '../../util/displayError'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import { generateUrl } from '@nextcloud/router'
import CopyToClipboardMixin from '../../mixins/CopyToClipboardMixin'
import PrinterIcon from 'vue-material-design-icons/Printer'
import CollectiveSettings from './CollectiveSettings'

export default {
	name: 'CollectiveListItem',

	components: {
		ActionButton,
		ActionLink,
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
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'collectiveParam',
			'collectives',
			'collectiveShareUrl',
			'isCollectiveAdmin',
			'isCollectiveSharable',
			'loading',
		]),

		isContactsInstalled() {
			return 'contacts' in this.OC.appswebroots
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
			this.$router.push(`/${encodeURIComponent(this.collective.name)}`,
				() => this.showSubpagesAndPrint(),
				(err) => {
					// Navigation is aborted since navigating to same route, but we still want to print
					if (err.name === 'NavigationDuplicated') {
						this.showSubpagesAndPrint()
					} else {
						throw err
					}
				}
			)
		},

		share(collective) {
			return this.$store.dispatch(SHARE_COLLECTIVE, collective)
				.catch(displayError(t('collectives', 'Could not share the collective')))
		},

		unshare(collective) {
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
