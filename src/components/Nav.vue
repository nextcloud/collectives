<template>
	<AppNavigation>
		<template v-if="loading('collectives')" #default>
			<EmptyContent icon="icon-loading" />
		</template>
		<template v-else #list>
			<AppNavigationCaption :title="t('collectives', 'Select a collective')" />
			<AppNavigationItem v-for="collective in collectives"
				:key="collective.circleUniqueId"
				:title="collective.name"
				:class="{active: isActive(collective)}"
				:to="`/${encodeURIComponent(collective.name)}`"
				:icon="icon(collective)"
				:force-menu="true"
				class="collectives_list_item">
				<template v-if="collective.emoji" #icon>
					{{ collective.emoji }}
				</template>
				<template v-if="collective.admin" #actions>
					<ActionButton icon="icon-delete" @click="trashCollective(collective)">
						{{ t('collectives', 'Delete') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
			<NewCollective />
		</template>
		<template v-if="!loading('collectives') && !loading('collectiveTrash')"
			#footer>
			<CollectiveTrash
				@restoreCollective="restoreCollective"
				@deleteCollective="deleteCollective" />
		</template>
	</AppNavigation>
</template>

<script>
import { mapGetters } from 'vuex'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import CollectiveTrash from '../components/CollectiveTrash'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import NewCollective from './NewCollective'
import displayError from '../util/displayError'

export default {
	name: 'Nav',
	components: {
		ActionButton,
		AppNavigation,
		AppNavigationItem,
		AppNavigationCaption,
		CollectiveTrash,
		EmptyContent,
		NewCollective,
	},
	computed: {
		...mapGetters([
			'loading',
			'collectives',
			'collectiveParam',
		]),
	},
	methods: {
		isActive(collective) {
			return this.collectiveParam === collective.name
		},
		newCollective(collective) {
			this.$emit('newCollective', collective)
		},
		icon(collective) {
			return collective.emoji ? '' : 'icon-star'
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
			return this.$store.dispatch('trashCollective', collective)
				.catch(displayError('Could not move the collective to trash'))
		},

		/**
		 * Restore a collective with the given name from trash
		 * @param {Object} collective Properties of the collective
		 * @returns {Promise}
		 */
		restoreCollective(collective) {
			return this.$store.dispatch('restoreCollective', collective)
				.catch(displayError('Could not restore collective from trash'))
		},

		/**
		 * Delete a collective with the given name from trash
		 * @param {Object} collective Properties of the collective
		 * @param {boolean} circle Whether to delete the circle as well
		 * @returns {Promise}
		 */
		deleteCollective(collective, circle) {
			return this.$store.dispatch('deleteCollective', { ...collective, circle })
				.catch(displayError('Could not delete collective from trash'))
		},
	},
}
</script>
