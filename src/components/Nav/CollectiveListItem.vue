<template>
	<AppNavigationItem
		:key="collective.circleId"
		:title="collective.name"
		:class="{active: isActive(collective)}"
		:to="`/${encodeURIComponent(collective.name)}`"
		:icon="icon"
		:force-menu="true"
		class="collectives_list_item">
		<template #icon>
			<EmojiPicker
				v-if="collective.level >= memberLevels.LEVEL_ADMIN"
				:show-preview="true"
				@select="updateEmoji">
				<button class="emoji"
					type="button"
					:aria-label="emojiTitle"
					:aria-haspopup="true"
					:title="emojiTitle"
					@click.prevent>
					{{ collective.emoji }}
				</button>
			</EmojiPicker>
			<button v-else
				class="emoji"
				type="button"
				@click.prevent>
				{{ collective.emoji }}
			</button>
		</template>
		<template #actions>
			<ActionButton v-if="!isPublic"
				v-show="!collectiveShare(collective)"
				:icon="shareIcon"
				:close-after-click="false"
				@click="share(collective)">
				{{ t('collectives', 'Share link') }}
			</ActionButton>
			<ActionButton v-if="!isPublic"
				v-show="collectiveShare(collective)"
				:icon="copyLinkIcon"
				:close-after-click="false"
				@click.stop.prevent="copyShare(collective)">
				{{ copyButtonText }}
			</ActionButton>
			<ActionButton v-if="!isPublic"
				v-show="collectiveShare(collective)"
				:icon="unshareIcon"
				:close-after-click="false"
				@click="unshare(collective)">
				{{ t('collectives', 'Unshare') }}
			</ActionButton>
			<ActionButton icon="icon-pages"
				:close-after-click="true"
				@click="print">
				{{ t('collectives', 'Print') }}
			</ActionButton>
			<ActionLink v-if="collective.level >= memberLevels.LEVEL_ADMIN && isContactsInstalled"
				:href="circleLink"
				icon="icon-circles">
				{{ t('collectives', 'Manage members') }}
			</ActionLink>
			<ActionButton v-if="collective.level >= memberLevels.LEVEL_ADMIN"
				icon="icon-delete"
				@click="trashCollective(collective)">
				{{ t('collectives', 'Delete') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import { UPDATE_COLLECTIVE, TRASH_COLLECTIVE, SHARE_COLLECTIVE, UNSHARE_COLLECTIVE } from '../../store/actions'
import displayError from '../../util/displayError'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import { generateUrl } from '@nextcloud/router'
import { memberLevels } from '../../constants'
import CopyToClipboardMixin from '../../mixins/CopyToClipboardMixin'

export default {
	name: 'CollectiveListItem',

	components: {
		ActionButton,
		ActionLink,
		AppNavigationItem,
		EmojiPicker,
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
			memberLevels,
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'collectives',
			'collectiveShare',
			'collectiveShareUrl',
			'loading',
		]),

		isContactsInstalled() {
			return 'contacts' in this.OC.appswebroots
		},

		circleLink() {
			return generateUrl('/apps/contacts/direct/circle/' + this.collective.circleId)
		},

		emojiTitle() {
			return this.collective.emoji ? t('collectives', 'Change emoji') : t('collectives', 'Add emoji')
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

		/**
		 * Update the emoji of a collective
		 *
		 * @param {string} emoji Emoji
		 * @return {Promise}
		 */
		updateEmoji(emoji) {
			const collective = this.collective
			collective.emoji = emoji
			return this.$store.dispatch(UPDATE_COLLECTIVE, collective)
				.catch(displayError('Could not update emoji for the collective'))
		},

		/**
		 * Trash a collective with the given name
		 *
		 * @param {object} collective Properties of the collective
		 * @return {Promise}
		 */
		trashCollective(collective) {
			if (this.collectiveParam === collective.name) {
				this.$router.push('/')
			}
			return this.$store.dispatch(TRASH_COLLECTIVE, collective)
				.catch(displayError('Could not move the collective to trash'))
		},

		print() {
			this.$router.push(`/${encodeURIComponent(this.collective.name)}`,
				() => {
					this.show('subpages')
					this.show('print')
				}
			)
		},

		share(collective) {
			return this.$store.dispatch(SHARE_COLLECTIVE, collective)
				.catch(displayError('Could not share the collective'))
		},

		unshare(collective) {
			return this.$store.dispatch(UNSHARE_COLLECTIVE, collective)
				.catch(displayError('Could not share the collective'))
		},

		copyShare(collective) {
			this.copyToClipboard(window.location.origin + this.collectiveShareUrl(collective))
		},
	},
}
</script>

<style scoped>
button.emoji {
	font-size: 15px;
	padding-left: 19px;
	background-color: transparent;
	border: none;
}
</style>
