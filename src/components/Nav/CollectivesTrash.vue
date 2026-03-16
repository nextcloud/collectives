<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="collectives-trash">
		<NcButton
			variant="tertiary"
			:aria-label="t('collectives', 'Deleted collectives')"
			class="collectives-trash-button"
			@click="openTrash">
			<template #icon>
				<DeleteIcon class="collectives-trash-button__icon" :size="20" />
			</template>
			{{ t('collectives', 'Deleted collectives') }}
		</NcButton>
		<NcDialog
			:open.sync="showModal"
			:name="t('collectives', 'Deleted collectives')"
			close-on-click-outside
			class="dialog__collectives-trash"
			size="large">
			<div class="modal__content">
				<NcEmptyContent v-if="loading('collectiveTrash')" :name="t('collectives', 'Loading…')">
					<template #icon>
						<NcLoadingIcon />
					</template>
				</NcEmptyContent>
				<NcEmptyContent
					v-else-if="!sortedTrashCollectives.length"
					:description="t('collectives', 'No deleted collectives.')">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
				</NcEmptyContent>
				<table v-else>
					<thead>
						<tr>
							<th class="header-title">
								{{ t('collectives', 'Name') }}
							</th>
							<th />
							<th v-if="showDeletedColumn" class="header-timestamp">
								{{ t('collectives', 'Deleted') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="collective in sortedTrashCollectives" :key="collective.id">
							<td class="item">
								<div class="item-icon">
									<div v-if="collective.emoji">
										{{ collective.emoji }}
									</div>
									<CollectivesIcon v-else :size="22" />
								</div>
								<div class="item-title">
									{{ collective.name }}
								</div>
							</td>
							<td class="actions">
								<NcButton
									:aria-label="t('collectives', 'Restore collective')"
									:disabled="!networkOnline"
									@click="restoreCollective(collective)">
									<template #icon>
										<RestoreIcon :size="20" />
									</template>
									{{ t('collectives', 'Restore') }}
								</NcButton>
								<NcActions :force-menu="true">
									<NcActionButton
										:close-after-click="true"
										:disabled="!networkOnline"
										@click="showDeleteModal(collective)">
										<template #icon>
											<DeleteIcon :size="20" />
										</template>
										{{ t('collectives', 'Delete permanently') }}
									</NcActionButton>
								</NcActions>
							</td>
							<td v-if="showDeletedColumn" class="timestamp">
								<span :title="titleDate(collective.trashTimestamp)">
									{{ formattedDate(collective.trashTimestamp) }}
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</NcDialog>

		<NcDialog
			v-if="deleteModal"
			:name="t('collectives', 'Permanently delete collective {collective}', { collective: modalCollective.name })"
			size="small"
			@closing="closeDeleteModal">
			<div class="modal__content">
				<div>
					{{ t('collectives', 'Delete corresponding team along with the collective?') }}
				</div>
			</div>
			<template #actions>
				<NcButton
					variant="error"
					:aria-label="t('collectives', 'Delete only collective')"
					:wide="true"
					@click="deleteCollective(modalCollective, false)">
					{{ t('collectives', 'Only collective') }}
				</NcButton>
				<NcButton
					v-if="isCollectiveOwner(modalCollective)"
					variant="error"
					:aria-label="t('collectives', 'Delete collective and team')"
					:wide="true"
					@click="deleteCollective(modalCollective, true)">
					{{ t('collectives', 'Collective and team') }}
				</NcButton>
				<NcButton
					v-else
					variant="primary"
					disabled
					:title="t('collectives', 'Only team owners can delete a team')"
					:wide="true">
					{{ t('collectives', 'Collective and team') }}
				</NcButton>
				<NcButton :aria-label="t('collectives', 'Cancel')" :wide="true" @click="closeDeleteModal">
					{{ t('collectives', 'Cancel') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'CollectivesTrash',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		CollectivesIcon,
		DeleteIcon,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		RestoreIcon,
	},

	props: {
		networkOnline: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const isMobile = useIsMobile()
		return { isMobile }
	},

	data() {
		return {
			showModal: false,
			deleteModal: false,
			modalCollective: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useCollectivesStore, [
			'isCollectiveOwner',
			'sortedTrashCollectives',
		]),

		showDeletedColumn() {
			return !this.isMobile && this.sortedTrashCollectives.some((collective) => collective.trashTimestamp)
		},

		titleDate() {
			return (timestamp) => timestamp ? moment.unix(timestamp).format('LLL') : ''
		},

		formattedDate() {
			return (timestamp) => timestamp ? moment.unix(timestamp).fromNow() : ''
		},
	},

	mounted() {
		subscribe('collectives:navigation:collective-trashed', this.onCollectiveTrashed)
		this.$highlightTimeoutId = null
	},

	unmounted() {
		unsubscribe('collectives:navigation:collective-trashed', this.onCollectiveTrashed)
		clearTimeout(this.$highlightTimeoutId)
	},

	methods: {
		t,

		...mapActions(useCollectivesStore, ['getTrashCollectives']),

		openTrash() {
			this.showModal = true
			this.getTrashCollectives()
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
	padding: 0 2px 4px 4px;

	.collectives-trash-button {
		width: calc(100% - 3px);
		padding: 0;

		&.highlight-animation {
			animation: highlight-animation 5s 1;
		}

		:deep(.button-vue__wrapper) {
			justify-content: start;
		}

		:deep(.button-vue__icon) {
			margin-left: -2px;
		}

		:deep(.button-vue__text) {
			margin-left: -2px;
			font-weight: normal;
		}
	}
}

:deep(.modal-wrapper--small) {
	.modal-container {
		width: 400px;
	}
}

.modal__content {
	margin: 15px;
}

table {
	width: 100%;
}

tr {
	display: flex;

	&:not(:last-child) {
		border-bottom: 1px solid var(--color-border);
	}
}

th, td {
	display: flex;
	height: var(--default-clickable-area);
	align-items: center;
	margin: 0 14px;
	padding: 2px 0;
}

th {
	color: var(--color-text-maxcontrast);

	&.header-title {
		padding-left: var(--default-clickable-area);
		flex: 1 1 auto;
	}

	&.header-timestamp {
		width: 110px;
	}
}

td {
	&.item {
		flex: 1 1 auto;

		.item-icon {
			display: flex;
			justify-content: center;
			width: var(--default-clickable-area);
		}

		.item-title {
			white-space: normal;
		}
	}

	&.actions {
		gap: 4px;
	}

	&.timestamp {
		width: 110px;
	}
}

:deep(.dialog__actions) {
	display: flex;
	flex-flow: column nowrap;
	gap: 10px;
}
</style>
