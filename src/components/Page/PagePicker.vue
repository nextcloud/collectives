<template>
	<NcModal @close="onClose">
		<div class="modal-content">
			<h2 class="oc-dialog-title">
				{{ t('collectives', 'Move page') }}
			</h2>
			<span class="crumbs">
				<div class="crumbs-home">
					<NcButton type="tertiary"
						:aria-label="t('collectives', 'Breadcrumb for Home')"
						:disabled="pageCrumbs.length === 0"
						class="crumb-button"
						@click="onClickHome">
						<template #icon>
							<HomeIcon :size="20" />
						</template>
					</NcButton>
				</div>
				<div v-for="(page, index) in pageCrumbs"
					:key="page.id"
					:aria-label="t('collectives', 'Breadcrumb for {page}', { page: page.title })"
					class="crumbs-level">
					<ChevronRightIcon :size="20" />
					<NcButton type="tertiary"
						:disabled="(index + 1) === pageCrumbs.length"
						class="crumb-button"
						@click="onClickPage(page)">
						{{ page.title }}
					</NcButton>
				</div>
			</span>
			<div class="picker-page-list">
				<ul v-if="subpages.length > 0">
					<li v-for="(page, index) in subpages"
						:id="`picker-page-${page.id}`"
						:key="page.id">
						<a :class="{'self': page.id === pageId}"
							:href="page.id === pageId ? false : '#'"
							class="picker-page-item"
							@click="onClickPage(page)">
							<div v-if="page.emoji" class="picker-page-icon">
								{{ page.emoji }}
							</div>
							<PageIcon v-else
								class="picker-page-icon"
								:size="20"
								fill-color="var(--color-background-darker)" />
							<div class="picker-page-title">
								{{ page.title }}
							</div>
							<div v-if="page.id === pageId" class="picker-move-buttons">
								<NcButton :disabled="index === 0"
									type="tertiary"
									@click="onClickUp">
									<template #icon>
										<ArrowUpIcon :size="20" />
									</template>
								</NcButton>
								<NcButton :disabled="index === (subpages.length - 1)"
									type="tertiary"
									@click="onClickDown">
									<template #icon>
										<ArrowDownIcon :size="20" />
									</template>
								</NcButton>
							</div>
						</a>
					</li>
				</ul>
			</div>
			<div class="picker-buttons">
				<NcButton type="primary" :disabled="loading" @click="onSelect">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('collectives', 'Move page here') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { mapGetters } from 'vuex'
import { NcButton, NcLoadingIcon, NcModal } from '@nextcloud/vue'
import ArrowDownIcon from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUpIcon from 'vue-material-design-icons/ArrowUp.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import PageIcon from '../Icon/PageIcon.vue'

export default {
	name: 'PagePicker',

	components: {
		ArrowDownIcon,
		ArrowUpIcon,
		ChevronRightIcon,
		HomeIcon,
		NcButton,
		NcLoadingIcon,
		NcModal,
		PageIcon,
	},

	props: {
		loading: {
			type: Boolean,
			default: false,
		},
		pageId: {
			type: Number,
			required: true,
		},
		parentId: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			subpages: [],
			selectedPageId: undefined,
		}
	},

	computed: {
		...mapGetters([
			'collectivePage',
			'pageById',
			'pageParents',
			'visibleSubpages',
		]),

		pageCrumbs() {
			return this.pageParents(this.selectedPageId)
		},
	},

	watch: {
		'selectedPageId'() {
			this.updateSubpages()
		},
	},

	mounted() {
		this.selectedPageId = this.parentId
		this.updateSubpages()

		window.addEventListener('keydown', this.handleKeyDown, true)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.handleKeyDown, true)
	},

	methods: {
		updateSubpages() {
			this.subpages = this.visibleSubpages(this.selectedPageId)

			// Add current page to top of subpages if not part of it yet
			if (!this.subpages.find(p => (p.id === this.pageId))) {
				this.subpages.unshift(this.pageById(this.pageId))
			}

			// Scroll current page into view (important when listing parent page)
			this.$nextTick(() => {
				document.getElementById(`picker-page-${this.pageId}`).scrollIntoView({ block: 'center' })
			})
		},

		/**
		 *
		 * @param {number} from old index
		 * @param {number} to new index
		 */
		swapSubpages(from, to) {
			const length = this.subpages.length - 1
			if (from >= 0 && from <= length && to >= 0 && to <= length) {
				this.subpages.splice(from, 1, this.subpages.splice(to, 1, this.subpages[from])[0])
			}

			// Scroll current page into view
			this.$nextTick(() => {
				document.getElementById(`picker-page-${this.pageId}`).scrollIntoView({ block: 'center' })
			})
		},

		onClickHome() {
			this.selectedPageId = this.collectivePage.id
		},

		/**
		 *
		 * @param {object} page page object
		 */
		onClickPage(page) {
			if (page.id === this.pageId) {
				// Don't allow to move pages below themselves
				return
			}
			this.selectedPageId = page.id
		},

		onClickDown() {
			const pageIndex = this.subpages.findIndex(p => (p.id === this.pageId))
			this.swapSubpages(pageIndex, pageIndex + 1)
		},

		onClickUp() {
			const pageIndex = this.subpages.findIndex(p => (p.id === this.pageId))
			this.swapSubpages(pageIndex, pageIndex - 1)
		},

		onClose() {
			this.$emit('close')
		},

		onSelect() {
			this.$emit('select', { parentId: this.selectedPageId, newIndex: this.subpages.findIndex(p => p.id === this.pageId) })
		},

		handleKeyDown(event) {
			if (event.key === 'ArrowDown') {
				event.preventDefault()
				this.onClickDown()
			}
			if (event.key === 'ArrowUp') {
				event.preventDefault()
				this.onClickUp()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.modal-container) {
	width: calc(100vw - 120px) !important;
	height: calc(100vw - 120px) !important;
	max-width: 600px !important;
	max-height: 500px !important;
}

.modal-content {
	display: flex;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	flex-direction: column;
	padding: 15px;
}

.crumbs {
	color: var(--color-text-maxcontrast);
	display: inline-flex;
	padding-right: 0;

	div {
		display: flex;
		text-overflow: ellipsis;
		white-space: nowrap;
		overflow: hidden;
		flex: 0 0 auto;
		max-width: 200px;

		.crumb-button {
			color: var(--color-text-maxcontrast);
		}

		&:hover {
			opacity: 1;
		}

		&.crumbs-home {
			flex-shrink: 0;
		}

		&.crumbs-level {
			display: inline-flex;
			min-width: 0;
			flex-shrink: 1;
		}

		&:last-child {
			flex-shrink: 0;

			.crumb-button {
				color: var(--color-main-text);
			}
		}
	}
}

.picker-page-list {
	display: inline-block;
	width: 100%;
	height: 100%;
	overflow-y: auto;
	flex: 1;

	li a {
		display: flex;

		&:not(:last-child) {
			border-bottom: 1px solid var(--color-border);
		}

		&:hover, &:focus, &:active {
			background-color: var(--color-background-hover);
		}

		// Element of the page that is to be moved
		&.self {
			background-color: var(--color-primary-light);
		}
	}

	li a.self {
		cursor: default;

		.picker-page-icon, .picker-page-title {
			cursor: default;
		}
	}

	.picker-page-icon {
		display: flex;
		justify-content: center;
		align-items: center;
		width: 44px;
	}

	.picker-page-title {
		padding: 14px 14px 14px 0;
		flex: 1;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	.picker-move-buttons {
		display: flex;
		align-items: center;
		padding: 0 12px;
	}
}

.picker-buttons {
	display: flex;
	justify-content: flex-end;
	padding-top: 10px;
}
</style>
