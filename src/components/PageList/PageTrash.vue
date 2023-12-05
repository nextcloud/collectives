<template>
	<div class="page-trash">
		<NcButton ref="pagetrashbutton"
			type="tertiary"
			class="page-trash-button"
			@click="toggleTrash">
			<template #icon>
				<DeleteIcon class="page-trash-button__icon" :size="20" />
			</template>
			{{ t('collectives', 'Deleted pages') }}
		</NcButton>
		<NcModal v-if="showModal"
			size="large"
			class="modal__page-trash"
			@close="closeTrash">
			<div class="modal__content">
				<h2>{{ t('collectives', 'Deleted pages') }}</h2>
				<NcEmptyContent v-if="!trashPages.length"
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
							<tr v-for="trashPage in trashPages" :key="trashPage.id">
								<td class="item">
									<div class="item-icon">
										<PageTemplateIcon v-if="isTemplate(trashPage)" :size="22" fill-color="var(--color-background-darker)" />
										<div v-else-if="trashPage.emoji">
											{{ trashPage.emoji }}
										</div>
										<PageIcon v-else :size="22" fill-color="var(--color-background-darker)" />
									</div>
									<div class="item-title">
										{{ trashPage.title }}
									</div>
								</td>
								<td class="actions">
									<NcButton @click="restorePage(trashPage)">
										<template #icon>
											<RestoreIcon :size="20" />
										</template>
										{{ t('collectives', 'Restore') }}
									</NcButton>
									<NcActions :force-menu="true">
										<NcActionButton :close-after-click="true" @click="deletePage(trashPage)">
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
		</NcModal>
	</div>
</template>

<script>
import { NcActions, NcActionButton, NcButton, NcEmptyContent, NcModal } from '@nextcloud/vue'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { showSuccess } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import PageIcon from '../Icon/PageIcon.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { EXPAND_PARENTS, RESTORE_PAGE, DELETE_PAGE } from '../../store/actions.js'
import { scrollToPage } from '../../util/scrollToElement.js'

export default {
	name: 'PageTrash',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcEmptyContent,
		NcModal,
		DeleteIcon,
		RestoreIcon,
		PageIcon,
		PageTemplateIcon,
	},

	mixins: [
		isMobile,
	],

	data() {
		return {
			showModal: false,
		}
	},

	computed: {
		...mapGetters([
			'trashPages',
		]),

		isTemplate() {
			return (trashPage) => {
				return trashPage.title === 'Template'
			}
		},

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
		...mapMutations([
			'setHighlightAnimationPageId',
		]),

		...mapActions({
			dispatchExpandParents: EXPAND_PARENTS,
			dispatchRestorePage: RESTORE_PAGE,
			dispatchDeletePage: DELETE_PAGE,
		}),

		toggleTrash() {
			this.showModal = !this.showModal
		},

		closeTrash() {
			this.showModal = false
		},

		restorePage(trashPage) {
			this.dispatchRestorePage({ pageId: trashPage.id })
				.then(() => {
					// Expand, scroll into view and highlight restored page
					this.$nextTick(() => {
						this.dispatchExpandParents(trashPage.id)
						scrollToPage(trashPage.id)
						this.setHighlightAnimationPageId(trashPage.id)
						setTimeout(() => {
							this.setHighlightAnimationPageId(null)
						}, 5000)
					})
				})
		},

		deletePage(trashPage) {
			this.dispatchDeletePage({ pageId: trashPage.id })
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
@import '../../css/animation.scss';

.page-trash {
	width: 100%;
	position: sticky;
	bottom: 0;
	margin-top: auto;
	background-color: var(--color-main-background);
	padding: 20px 4px 12px 4px;

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
	margin: 2vw;

	&__empty {
		margin-top: 25px !important;
	}
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
	height: 55px;
	align-items: center;
	margin: 0 14px;
}

th {
	color: var(--color-text-maxcontrast);

	&.header-title {
		padding-left: 44px;
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
			width: 44px;
		}

		.item-title {
			white-space: normal;
		}

	}

	&.timestamp {
		width: 110px;
	}
}
</style>
