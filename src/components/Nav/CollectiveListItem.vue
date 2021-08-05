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
import { UPDATE_COLLECTIVE, TRASH_COLLECTIVE } from '../../store/actions'
import displayError from '../../util/displayError'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import { generateUrl } from '@nextcloud/router'
import { memberLevels } from '../../constants'

export default {
	name: 'CollectiveListItem',

	components: {
		ActionButton,
		ActionLink,
		AppNavigationItem,
		EmojiPicker,
	},

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
			'collectives',
		]),

		isContactsInstalled() {
			return 'circles' in this.OC.appswebroots
		},

		circleLink() {
			return generateUrl('/apps/circles')
		},

		emojiTitle() {
			return this.collective.emoji ? t('collectives', 'Change emoji') : t('collectives', 'Add emoji')
		},

		icon() {
			return this.collective.emoji ? '' : 'icon-collectives'
		},
	},

	methods: {
		...mapMutations(['show']),

		isActive(collective) {
			return this.collectiveParam === collective.name
		},

		newCollective(collective) {
			this.$emit('newCollective', collective)
		},

		/**
		 * Update the emoji of a collective
		 * @param {String} emoji Emoji
		 * @returns {Promise}
		 */
		updateEmoji(emoji) {
			const collective = this.collective
			collective.emoji = emoji
			return this.$store.dispatch(UPDATE_COLLECTIVE, collective)
				.catch(displayError('Could not update emoji for the collective'))
		},

		/**
		 * Trash a collective with the given name
		 * @param {Object} collective Properties of the collective
		 * @returns {Promise}
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
