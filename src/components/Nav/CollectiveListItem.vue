<template>
	<AppNavigationItem
		:key="collective.circleId"
		:title="collective.name"
		:class="{active: isActive(collective)}"
		:to="`/${encodeURIComponent(collective.name)}`"
		:icon="icon(collective)"
		:force-menu="true"
		class="collectives_list_item">
		<template v-if="collective.emoji" #icon>
			{{ collective.emoji }}
		</template>
		<template #actions>
			<ActionButton icon="icon-pages"
				:close-after-click="true"
				@click="print">
				{{ t('collectives', 'Print') }}
			</ActionButton>
			<ActionLink v-if="collective.admin && isContactsInstalled"
				:href="circleLink"
				icon="icon-circles">
				{{ t('collectives', 'Manage members') }}
			</ActionLink>
			<ActionButton v-if="collective.admin"
				icon="icon-delete"
				@click="trashCollective(collective)">
				{{ t('collectives', 'Delete') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import { TRASH_COLLECTIVE } from '../../store/actions'
import displayError from '../../util/displayError'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'CollectiveListItem',

	components: {
		ActionButton,
		ActionLink,
		AppNavigationItem,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},
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
	},

	methods: {
		...mapMutations(['show']),

		isActive(collective) {
			return this.collectiveParam === collective.name
		},

		newCollective(collective) {
			this.$emit('newCollective', collective)
		},

		icon(collective) {
			return collective.emoji ? '' : 'icon-collectives'
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
