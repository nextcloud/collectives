<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="page-trash">
		<NcButton
			ref="pagetrashbutton"
			variant="tertiary"
			:aria-label="t('collectives', 'Show deleted pages')"
			class="page-trash-button"
			@click="openTrash">
			<template #icon>
				<DeleteIcon class="page-trash-button__icon" :size="20" />
			</template>
			{{ t('collectives', 'Deleted pages') }}
		</NcButton>
		<NcDialog
			:open.sync="showModal"
			:name="t('collectives', 'Deleted pages')"
			class="dialog__page-trash"
			size="large">
			<div class="modal__content">
				<NcEmptyContent
					v-if="!sortedTrashPages.length"
					class="modal__content_empty"
					:description="t('collectives', 'No deleted pages.')">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
				</NcEmptyContent>
				<template v-else>
					<table>
						<thead>
							<tr>
								<th class="header-title">
									{{ t('collectives', 'Title') }}
								</th>
								<th />
								<th v-if="!isMobile" class="header-timestamp">
									{{ t('collectives', 'Deleted') }}
								</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="trashPage in sortedTrashPages" :key="trashPage.id">
								<td class="item">
									<div class="item-icon">
										<div v-if="trashPage.emoji">
											{{ trashPage.emoji }}
										</div>
										<PageIcon v-else :size="22" fill-color="var(--color-background-darker)" />
									</div>
									<div class="item-title">
										{{ trashPage.title }}
									</div>
								</td>
								<td class="actions">
									<NcButton
										:aria-label="t('collectives', 'Restore page')"
										:disabled="!networkOnline"
										@click="onClickRestore(trashPage)">
										<template #icon>
											<RestoreIcon :size="20" />
										</template>
										{{ t('collectives', 'Restore') }}
									</NcButton>
									<NcActions :force-menu="true">
										<NcActionButton
											:close-after-click="true"
											:disabled="!networkOnline"
											@click="onClickDelete(trashPage)">
											<template #icon>
												<DeleteIcon :size="20" />
											</template>
											{{ t('collectives', 'Delete permanently') }}
										</NcActionButton>
									</NcActions>
								</td>
								<td v-if="!isMobile" class="timestamp">
									<span :title="titleDate(trashPage.trashTimestamp)">
										{{ formattedDate(trashPage.trashTimestamp) }}
									</span>
								</td>
							</tr>
						</tbody>
					</table>
				</template>
			</div>
		</NcDialog>
	</div>
</template>

<script>
import { showSuccess } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import { NcActionButton, NcActions, NcButton, NcDialog, NcEmptyContent } from '@nextcloud/vue'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import PageIcon from '../Icon/PageIcon.vue'
import { usePagesStore } from '../../stores/pages.js'
import { scrollToPage } from '../../util/scrollToElement.js'

export default {
	name: 'PageTrash',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcDialog,
		NcEmptyContent,
		DeleteIcon,
		RestoreIcon,
		PageIcon,
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
		}
	},

	computed: {
		...mapState(usePagesStore, ['sortedTrashPages']),

		titleDate() {
			return (timestamp) => {
				return moment.unix(timestamp).format('LLL')
			}
		},

		formattedDate() {
			return (timestamp) => {
				return moment.unix(timestamp).fromNow()
			}
		},
	},

	mounted() {
		subscribe('collectives:page-list:page-trashed', this.onPageTrashed)
		this.$highlightTimeoutId = null
	},

	unmounted() {
		unsubscribe('collectives:page-list:page-trashed', this.onPageTrashed)
		clearTimeout(this.$highlightTimeoutId)
	},

	methods: {
		...mapActions(usePagesStore, [
			'deletePage',
			'expandParents',
			'restorePage',
			'setHighlightAnimationPageId',
		]),

		openTrash() {
			this.showModal = true
		},

		onClickRestore(trashPage) {
			this.restorePage({ pageId: trashPage.id })
				.then(() => {
					// Expand, scroll into view and highlight restored page
					this.$nextTick(() => {
						this.expandParents(trashPage.id)
						scrollToPage(trashPage.id)
						this.setHighlightAnimationPageId(trashPage.id)
						setTimeout(() => {
							this.setHighlightAnimationPageId(null)
						}, 5000)
					})
				})
		},

		onClickDelete(trashPage) {
			this.deletePage({ pageId: trashPage.id })
			showSuccess(t('collectives', 'Page permanently deleted'))
		},

		onPageTrashed() {
			if (this.$highlightTimeoutId) {
				// clear former timeout and remove class to allow re-highlighting the button
				clearTimeout(this.$highlightTimeoutId)
				this.$refs.pagetrashbutton.$el.classList.remove('highlight-animation')
			}
			this.$nextTick(() => {
				this.$refs.pagetrashbutton.$el.classList.add('highlight-animation')
				this.$highlightTimeoutId = setTimeout(() => {
					this.$refs.pagetrashbutton.$el.classList.remove('highlight-animation')
					this.$highlightTimeoutId = null
				}, 5000)
			})
		},
	},
}
</script>

<style scoped lang="scss">
@use '../../css/animation';

.page-trash {
	position: sticky;
	bottom: 0;
	width: 100%;
	margin-top: auto;
	background-color: var(--color-main-background);
	padding: 0 2px 4px 4px;

	.page-trash-button {
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

.modal__content {
	margin-bottom: 12px;
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
</style>
