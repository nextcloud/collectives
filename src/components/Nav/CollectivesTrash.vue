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
		<Modal v-if="deleteModal" @close="closeDeleteModal">
			<div class="modal__content">
				<h2 class="modal__content__title">
					{{ t('collectives', 'Permanently delete collective »{collective}«', { collective: modalCollective.name }) }}
				</h2>
				<div class="modal__content__content">
					<p>{{ t('collectives', 'Delete corresponding circle along with the collective?') }}</p>
				</div>
				<div class="modal__content__buttonrow threebuttons">
					<button @click="closeDeleteModal">
						{{ t('collectives', 'Cancel') }}
					</button>
					<button class="error primary" @click="deleteCollective(modalCollective, false)">
						{{ t('collectives', 'Only collective') }}
					</button>
					<button v-if="modalCollective.level >= memberLevels.LEVEL_OWNER"
						class="error primary"
						@click="deleteCollective(modalCollective, true)">
						{{ t('collectives', 'Collective and circle') }}
					</button>
					<button v-else
						class="error primary"
						disabled
						:title="t('collectives', 'Only circle owners can delete a circle')">
						{{ t('collectives', 'Collective and circle') }}
					</button>
				</div>
			</div>
		</Modal>
	</AppNavigationSettings>
</template>

<script>
import { mapState } from 'vuex'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { memberLevels } from '../../constants'

export default {
	name: 'CollectivesTrash',
	components: {
		ActionButton,
		AppNavigationItem,
		AppNavigationSettings,
		Modal,
	},
	data() {
		return {
			deleteModal: false,
			modalCollective: null,
			memberLevels,
		}
	},
	computed: mapState({
		trashCollectives: (state) => state.collectives.trashCollectives,
	}),
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

.modal__content {
	background: var(--color-main-background);
	color: var(--color-text-light);
	border-radius: var(--border-radius-large);
	box-shadow: 0 0 30px var(--color-box-shadow);
	padding: 15px;
	font-size: 100%;
	min-width: 200px;
	max-height: calc(100% - 20px);
	max-width: calc(100% - 20px);
	overflow: auto;
	position: relative;
}

.modal__content__title {
	background: var(--color-main-background);
}

.modal__content__buttonrow {
	position: relative;
	display: flex;
	background: transparent;
	right: 0;
	bottom: 0;
	padding: 0;
	padding-top: 10px;
	box-sizing: border-box;
	width: 100%;
	background-image: linear-gradient(rgba(255, 255, 255, 0.0), var(--color-main-background));

	&.threebuttons {
		justify-content: space-between;
	}

	button {
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		height: 44px;
		min-width: 44px;
	}
}

.modal__content__content {
	width: 100%;
	max-width: 550px;
}
</style>
