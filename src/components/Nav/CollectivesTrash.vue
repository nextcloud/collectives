<template>
	<AppNavigationSettings id="collectives-trash" :title="t('collectives', 'Deleted collectives')">
		<ul class="app-navigation__list">
			<AppNavigationItem v-for="collective in trashCollectives"
				:key="collective.circleId"
				:title="collective.name"
				:icon="icon(collective)"
				:force-menu="true"
				class="collectives_trash_list_item">
				<template v-if="collective.emoji" #icon>
					{{ collective.emoji }}
				</template>
				<template #actions>
					<ActionButton icon="icon-history" :close-after-click="true" @click="restoreCollective(collective)">
						{{ t('collectives', 'Restore') }}
					</ActionButton>
					<ActionButton icon="icon-delete" :close-after-click="true" @click="showDeleteModal(collective)">
						{{ t('collectives', 'Delete permanently') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
		</ul>
		<Modal v-if="deleteModal" size="small" @close="closeDeleteModal">
			<div class="modal__content">
				<h2>
					{{ t('collectives', 'Permanently delete collective »{collective}«', { collective: modalCollective.name }) }}
				</h2>
				<div>
					{{ t('collectives', 'Delete corresponding circle along with the collective?') }}
				</div>
				<div class="three_buttons">
					<Button @click="closeDeleteModal">
						{{ t('collectives', 'Cancel') }}
					</Button>
					<Button type="error" @click="deleteCollective(modalCollective, false)">
						{{ t('collectives', 'Only collective') }}
					</Button>
					<Button v-if="isCollectiveOwner(modalCollective)"
						type="error"
						@click="deleteCollective(modalCollective, true)">
						{{ t('collectives', 'Collective and circle') }}
					</Button>
					<Button v-else
						type="primary"
						disabled
						:title="t('collectives', 'Only circle owners can delete a circle')">
						{{ t('collectives', 'Collective and circle') }}
					</Button>
				</div>
			</div>
		</Modal>
	</AppNavigationSettings>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'
import Button from '@nextcloud/vue/dist/Components/Button'
import Modal from '@nextcloud/vue/dist/Components/Modal'

export default {
	name: 'CollectivesTrash',
	components: {
		ActionButton,
		AppNavigationItem,
		AppNavigationSettings,
		Button,
		Modal,
	},
	data() {
		return {
			deleteModal: false,
			modalCollective: null,
		}
	},
	computed: {
		...mapState({
			trashCollectives: (state) => state.collectives.trashCollectives,
		}),
		...mapGetters([
			'isCollectiveOwner',
		]),
	},

	methods: {
		icon(collective) {
			return collective.emoji ? '' : 'icon-star'
		},
		restoreCollective(collective) {
			this.$emit('restore-collective', collective)
		},
		deleteCollective(collective, circle) {
			this.$emit('delete-collective', collective, circle)
			this.closeDeleteModal()
		},
		showDeleteModal(collective) {
			this.modalCollective = collective
			this.deleteModal = true
		},
		closeDeleteModal() {
			this.modalCollective = null
			this.deleteModal = false
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep #app-settings__header .settings-button .settings-button__icon {
	visibility: hidden;
	background-image: var(--icon-delete-000);
}

::v-deep #app-settings__header .settings-button .settings-button__icon:before {
	visibility: visible;
	content: '';
	width: 44px;
	height: 44px;
	background-image: var(--icon-delete-000);
	background-repeat: no-repeat;
	background-position: center;
	margin-left: 10px;
}

::v-deep .modal-wrapper--small {
	.modal-container {
		max-width: 90%;
		width: 600px;
	}
}

.modal__content {
	margin: 15px;
}

.three_buttons {
	display: flex;
	justify-content: space-between;
	padding-top: 10px;
}
</style>
