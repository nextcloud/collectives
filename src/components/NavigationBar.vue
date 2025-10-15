<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation>
		<template v-if="loading('collectives')" #list>
			<NcAppNavigationCaption :name="t('collectives', 'Select a collective')" />
			<SkeletonLoading type="items" :count="3" />
		</template>
		<template v-else #list>
			<NcAppNavigationCaption :name="t('collectives', 'Select a collective')" />
			<CollectiveListItem
				v-for="collective in sortedCollectives"
				v-show="!collective.deleted"
				:key="collective.id"
				:collective="collective"
				:network-online="networkOnline" />
			<li>
				<NcAppNavigationNew
					v-if="!isPublic"
					:text="t('collectives', 'New collective')"
					:disabled="!networkOnline"
					variant="secondary"
					class="new-collective-button"
					@click="onOpenNewCollectiveModal">
					<template #icon>
						<PlusIcon />
					</template>
				</NcAppNavigationNew>
			</li>
		</template>
		<template #footer>
			<CollectivesTrash
				v-if="displayTrash"
				:network-online="networkOnline"
				@restore-collective="onRestoreCollective"
				@delete-collective="onDeleteCollective" />
			<CollectivesGlobalSettings v-if="!isPublic" :network-online="networkOnline" />
		</template>
		<NewCollectiveModal v-if="showNewCollectiveModal" @close="onCloseNewCollectiveModal" />
		<CollectiveMembersModal
			v-if="showCollectiveMembersModal"
			:collective="membersCollective"
			@close="onCloseCollectiveMembersModal" />
		<TemplatesDialog v-if="templatesCollectiveId" />
	</NcAppNavigation>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { NcAppNavigation, NcAppNavigationCaption, NcAppNavigationNew } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CollectiveListItem from './Nav/CollectiveListItem.vue'
import CollectiveMembersModal from './Nav/CollectiveMembersModal.vue'
import CollectivesGlobalSettings from './Nav/CollectivesGlobalSettings.vue'
import CollectivesTrash from './Nav/CollectivesTrash.vue'
import NewCollectiveModal from './Nav/NewCollectiveModal.vue'
import TemplatesDialog from './Nav/TemplatesDialog.vue'
import SkeletonLoading from './SkeletonLoading.vue'
import { useNetworkState } from '../composables/useNetworkState.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { useRootStore } from '../stores/root.js'
import displayError from '../util/displayError.js'

export default {
	name: 'NavigationBar',

	components: {
		NcAppNavigation,
		NcAppNavigationCaption,
		NcAppNavigationNew,
		CollectiveListItem,
		CollectiveMembersModal,
		CollectivesGlobalSettings,
		CollectivesTrash,
		NewCollectiveModal,
		SkeletonLoading,
		PlusIcon,
		TemplatesDialog,
	},

	setup() {
		const { networkOnline } = useNetworkState()
		return { networkOnline }
	},

	data() {
		return {
			showNewCollectiveModal: false,
		}
	},

	computed: {
		...mapState(useRootStore, ['isPublic', 'loading']),
		...mapState(useCollectivesStore, [
			'membersCollective',
			'sortedCollectives',
			'sortedTrashCollectives',
			'templatesCollectiveId',
		]),

		displayTrash() {
			return !this.isPublic
				&& this.sortedTrashCollectives.length
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
		...mapActions(useCollectivesStore, [
			'deleteCollective',
			'restoreCollective',
			'setMembersCollectiveId',
		]),

		/**
		 * Restore a collective with the given name from trash
		 *
		 * @param {object} collective Properties of the collective
		 * @return {Promise}
		 */
		onRestoreCollective(collective) {
			return this.restoreCollective(collective)
				.catch(displayError('Could not restore collective from trash'))
		},

		/**
		 * Delete a collective with the given name from trash
		 *
		 * @param {object} collective Properties of the collective
		 * @param {boolean} circle Whether to delete the team as well
		 * @return {Promise}
		 */
		onDeleteCollective(collective, circle) {
			return this.deleteCollective({ ...collective, circle })
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
.new-collective-button {
	width: max-content;
}

/* Don't print list of collectives */
@media print {
	#app-navigation-vue {
		display: none !important;
	}
}
</style>
