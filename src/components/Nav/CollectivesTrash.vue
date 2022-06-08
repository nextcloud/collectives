<template>
	<div id="collectives-trash"
		v-click-outside="clickOutsideConfig"
		:class="{ open }">
		<div id="collectives-trash__header">
			<Button type="button" class="collectives-trash-button" @click="toggleTrash">
				<template #icon>
					<DeleteIcon class="collectives-trash-button__icon" :size="20" decorative />
				</template>
				<span class="collectives-trash-button__label">
					{{ t('collectives', 'Deleted collectives') }}
				</span>
			</Button>
		</div>
		<transition name="slide-up">
			<div v-show="open" id="collectives-trash__content">
				<ul class="app-navigation__list">
					<AppNavigationItem v-for="collective in trashCollectives"
						:key="collective.circleId"
						:title="collective.name"
						:force-menu="true"
						class="collectives_trash_list_item">
						<template v-if="collective.emoji" #icon>
							{{ collective.emoji }}
						</template>
						<template v-else #icon>
							<CollectivesIcon :size="20" />
						</template>
						<template #actions>
							<ActionButton :close-after-click="true" @click="restoreCollective(collective)">
								<template #icon>
									<RestoreIcon :size="20" />
								</template>
								{{ t('collectives', 'Restore') }}
							</ActionButton>
							<ActionButton :close-after-click="true" @click="showDeleteModal(collective)">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
								{{ t('collectives', 'Delete permanently') }}
							</ActionButton>
						</template>
					</AppNavigationItem>
				</ul>
			</div>
		</transition>

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
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import Button from '@nextcloud/vue/dist/Components/Button'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import CollectivesIcon from '../Icon/CollectivesIcon'
import DeleteIcon from 'vue-material-design-icons/Delete'
import RestoreIcon from 'vue-material-design-icons/Restore'
import { directive as ClickOutside } from 'v-click-outside'

export default {
	name: 'CollectivesTrash',
	directives: {
		ClickOutside,
	},
	components: {
		ActionButton,
		AppNavigationItem,
		Button,
		CollectivesIcon,
		DeleteIcon,
		Modal,
		RestoreIcon,
	},
	data() {
		return {
			open: false,
			deleteModal: false,
			modalCollective: null,
			clickOutsideConfig: {
				handler: this.closeTrash,
			},
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
		toggleTrash() {
			this.open = !this.open
		},
		closeTrash() {
			this.open = false
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
#collectives-trash {
	margin-top: auto;

	&__header {
		box-sizing: border-box;
		background-color: var(--color-main-background);

		.collectives-trash-button {
			display: flex;
			flex: 1 1 0;
			height: 44px;
			width: 100%;
			padding: 0;
			margin: 0;
			background-color: var(--color-main-background);
			box-shadow: none;
			border: 0;
			border-radius: 0;
			color: var(--color-main-text);
			padding-right: 14px;
			line-height: 44px;

			::v-deep .button-vue__wrapper {
				width: 100%;
				justify-content: start;
			}

			&:hover,
			&:focus {
				background-color: var(--color-background-hover);
				border-radius: 0;
			}

			&__icon {
				width: 44px;
				height: 44px;
				min-width: 44px;
			}
			&__label {
				overflow: hidden;
				max-width: 100%;
				white-space: nowrap;
				text-overflow: ellipsis;
				text-align: left;
				font-weight: normal;
				font-size: 100%;
			}
		}
	}

	&__content {
		display: block;
		padding: 10px;
		background-color: var(--color-main-background);
		/* Restrict height of trash an make scrollable */
		max-height: 300px;
		overflow-y: auto;
		box-sizing: border-box;
	}

	.slide-up-leave-active,
	.slide-up-enter-active {
		transition-duration: var(--animation-slow);
		transition-property: max-height, padding;
		overflow-y: hidden !important;
	}

	.slide-up-enter,
	.slide-up-leave-to {
		max-height: 0 !important;
		padding: 0 10px !important;
	}
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
