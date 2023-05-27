<template>
	<div id="page-trash"
		v-click-outside="clickOutsideConfig">
		<div id="page-trash__header">
			<NcButton type="tertiary" class="page-trash-button" @click="toggleTrash">
				<template #icon>
					<DeleteIcon class="page-trash-button__icon" :size="20" />
				</template>
				{{ t('collectives', 'Deleted pages') }}
			</NcButton>
		</div>
		<transition name="slide-up">
			<div v-show="open" id="page-trash__content">
				<div v-for="trashPage in trashPages"
					:key="`page-trash-${trashPage.id}`"
					class="app-content-list-item"
					:class="{ mobile: isMobile }">
					<div class="app-content-list-item-icon">
						<PageTemplateIcon v-if="isTemplate(trashPage)" :size="22" fill-color="var(--color-background-darker)" />
						<div v-else-if="trashPage.emoji" class="item-icon-emoji">
							{{ trashPage.emoji }}
						</div>
						<PageIcon v-else :size="22" fill-color="var(--color-background-darker)" />
					</div>
					<a class="app-content-list-item-link">
						<div :ref="`page-title-${trashPage.id}`"
							v-tooltip="pageTitleIfTruncated(trashPage)"
							class="app-content-list-item-line-one"
							:class="{ 'template': isTemplate(trashPage) }">
							{{ pageTitleString(trashPage) }}
						</div>
					</a>
					<div class="page-list-item-actions">
						<NcActions>
							<NcActionButton :close-after-click="true" @click="restorePage(trashPage)">
								<template #icon>
									<RestoreIcon :size="20" />
								</template>
								{{ t('collectives', 'Restore') }}
							</NcActionButton>
							<NcActionButton :close-after-click="true" @click="deletePage(trashPage)">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
								{{ t('collectives', 'Delete permanently') }}
							</NcActionButton>
						</NcActions>
					</div>
				</div>
			</div>
		</transition>
	</div>
</template>

<script>
import { NcActions, NcActionButton, NcButton } from '@nextcloud/vue'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import PageIcon from '../Icon/PageIcon.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import { directive as ClickOutside } from 'v-click-outside'
import { mapActions, mapGetters } from 'vuex'
import { RESTORE_PAGE, DELETE_PAGE } from '../../store/actions.js'

export default {
	name: 'PageTrash',

	directives: {
		ClickOutside,
	},

	components: {
		NcActions,
		NcActionButton,
		NcButton,
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
			open: false,
			clickOutsideConfig: {
				handler: this.closeTrash,
			},
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

		pageTitleIsTruncated() {
			return (trashPage) => {
				return false
				// TODO: ref not working
				// return this.$refs[`page-title-${trashPage.id}`].scrollWidth > this.$refs[`page-title-${trashPage.id}`].clientWidth
			}
		},

		pageTitleIfTruncated() {
			return (trashPage) => {
				return this.pageTitleIsTruncated(trashPage) ? this.pageTitleString : null
			}
		},

		pageTitleString() {
			return (trashPage) => {
				return this.isTemplate(trashPage) ? t('collectives', 'Template') : trashPage.title
			}
		},
	},

	methods: {
		...mapActions({
			dispatchRestorePage: RESTORE_PAGE,
			dispatchDeletePage: DELETE_PAGE,
		}),

		toggleTrash() {
			this.open = !this.open
		},

		closeTrash() {
			this.open = false
		},

		restorePage(trashPage) {
			this.dispatchRestorePage({ pageId: trashPage.id })
			this.closeTrash()
		},

		deletePage(trashPage) {
			this.dispatchDeletePage({ pageId: trashPage.id })
			this.closeTrash()
		},
	},
}
</script>

<style scoped lang="scss">
#page-trash {
	width: 100%;
	position: absolute;
	bottom: 0;
	// margin-top: auto;

	&__header {
		box-sizing: border-box;
		margin: 0 3px 3px 3px;
		padding-top: calc(var(--default-grid-baseline, 4px) * 2);

		.page-trash-button {
			display: flex;
			flex: 1 1 0;
			height: 44px;
			width: 100%;
			padding: 0;
			margin: 0;
			// background-color: var(--color-main-background);
			box-shadow: none;
			border: 0;
			border-radius: var(--border-radius-pill);
			color: var(--color-main-text);
			padding-right: 14px;
			line-height: 44px;

			:deep(.button-vue__wrapper) {
				width: 100%;
				justify-content: start;
			}

			&:hover,
			&:focus {
				background-color: var(--color-background-hover);
			}

			&__icon {
				width: 44px;
				height: 44px;
				min-width: 44px;
			}

			:deep(.button-vue__text) {
				overflow: hidden;
				white-space: nowrap;
				text-overflow: ellipsis;
				font-weight: normal;
			}
		}
	}

	&__content {
		display: block;
		padding: 10px 4px;
		/* Restrict height of trash and make scrollable */
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
		padding: 0 4px !important;
	}

	.app-content-list-item {
		box-sizing: border-box;
		height: 44px;
		// border-bottom: 4px solid var(--color-main-background);
		margin-bottom: 4px;

		padding-left: 0;
		border-radius: var(--border-radius-large);
		cursor: default;

		&:hover, &:focus, &:active {
			background-color: var(--color-background-hover);
		}

		&.mobile, &:hover, &:focus, &:active {
			// Shorter width to prevent collision with actions
			.app-content-list-item-link {
				width: calc(100% - 24px);
			}

			.page-list-item-actions {
				visibility: visible;
			}
		}

		.app-content-list-item-icon {
			display: flex;
			justify-content: center;
			align-items: center;
			// Emojis are too big with default 1.5em
			font-size: 1.3em;
			cursor: default;

			.item-icon-emoji {
				&.landing-page {
					margin: -3px 0;
				}
			}
		}

		.app-content-list-item-line-one {
			padding-left: 40px;
			cursor: default;
		}

		.app-content-list-item-link {
			width: 100%;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}

	.page-list-item-actions {
		visibility: hidden;
		display: flex;
		position: absolute;
		top: 0;
		right: 0;
		margin: 0;
	}
}
</style>
