<template>
	<NcAppNavigation>
		<template v-if="loading('collectives')" #list>
			<NcAppNavigationCaption :name="t('collectives', 'Select a collective')" />
			<SkeletonLoading type="items" :count="3" />
		</template>
		<template v-else #list>
			<NcAppNavigationCaption :name="t('collectives', 'Select a collective')" />
			<CollectiveListItem v-for="collective in collectives"
				v-show="!collective.deleted"
				:key="collective.id"
				:collective="collective" />
			<NcButton v-if="!isPublic"
				:aria-label="t('collectives', 'Create a new collective')"
				@click="onOpenNewCollectiveModal">
				<template #icon>
					<PlusIcon />
				</template>
				{{ t('collectives', 'New collective') }}
			</NcButton>
		</template>
		<template #footer>
			<CollectivesTrash v-if="displayTrash"
				@restore-collective="restoreCollective"
				@delete-collective="deleteCollective" />
			<CollectivesGlobalSettings v-if="!isPublic" />
		</template>
		<NewCollectiveModal v-if="showNewCollectiveModal" @close="onCloseNewCollectiveModal" />
		<CollectiveMembersModal v-if="showCollectiveMembersModal"
			:collective="membersCollective"
			@close="onCloseCollectiveMembersModal" />
	</NcAppNavigation>
</template>

<script>
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { RESTORE_COLLECTIVE, DELETE_COLLECTIVE } from '../store/actions.js'
import { NcAppNavigation, NcAppNavigationCaption, NcButton } from '@nextcloud/vue'
import CollectiveMembersModal from './Nav/CollectiveMembersModal.vue'
import CollectiveListItem from './Nav/CollectiveListItem.vue'
import CollectivesGlobalSettings from './Nav/CollectivesGlobalSettings.vue'
import CollectivesTrash from './Nav/CollectivesTrash.vue'
import NewCollectiveModal from './Nav/NewCollectiveModal.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import displayError from '../util/displayError.js'
import SkeletonLoading from './SkeletonLoading.vue'

export default {
	name: 'Navigation',

	components: {
		NcAppNavigation,
		NcAppNavigationCaption,
		NcButton,
		CollectiveListItem,
		CollectiveMembersModal,
		CollectivesGlobalSettings,
		CollectivesTrash,
		NewCollectiveModal,
		SkeletonLoading,
		PlusIcon,
	},

	data() {
		return {
			showNewCollectiveModal: false,
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'loading',
			'collectives',
			'membersCollective',
			'trashCollectives',
		]),

		displayTrash() {
			return !this.isPublic
				&& this.trashCollectives.length
				&& !this.loading('collectives')
				&& !this.loading('collectiveTrash')
		},

		showCollectiveMembersModal() {
			return !!this.membersCollective
		},
	},

	mounted() {
		subscribe('open-new-collective-modal', this.onOpenNewCollectiveModal)
	},

	unmounted() {
		unsubscribe('open-new-collective-modal', this.onOpenNewCollectiveModal)
	},

	methods: {
		...mapMutations([
			'setMembersCollectiveId',
		]),

		...mapActions({
			dispatchRestoreCollective: RESTORE_COLLECTIVE,
			dispatchDeleteCollective: DELETE_COLLECTIVE,
		}),

		/**
		 * Restore a collective with the given name from trash
		 *
		 * @param {object} collective Properties of the collective
		 * @return {Promise}
		 */
		restoreCollective(collective) {
			return this.dispatchRestoreCollective(collective)
				.catch(displayError('Could not restore collective from trash'))
		},

		/**
		 * Delete a collective with the given name from trash
		 *
		 * @param {object} collective Properties of the collective
		 * @param {boolean} circle Whether to delete the circle as well
		 * @return {Promise}
		 */
		deleteCollective(collective, circle) {
			return this.dispatchDeleteCollective({ ...collective, circle })
				.catch(displayError('Could not delete collective from trash'))
		},

		onOpenNewCollectiveModal() {
			this.showNewCollectiveModal = true
		},

		onCloseNewCollectiveModal() {
			this.showNewCollectiveModal = false
		},

		onCloseCollectiveMembersModal() {
			this.setMembersCollectiveId(null)
		},
	},
}
</script>

<style scoped>
/* Don't print list of collectives */
@media print {
	#app-navigation-vue {
		display: none !important;
	}
}
</style>
