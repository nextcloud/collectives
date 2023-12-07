<template>
	<NcModal @close="onClose">
		<div class="modal-content">
			<h2 class="oc-dialog-title">
				{{ t('collectives', 'Copy or move page') }}
			</h2>
			<span class="crumbs">
				<div class="crumbs-home">
					<NcButton type="tertiary"
						:aria-label="t('collectives', 'Breadcrumb for list of collectives')"
						:disabled="!selectedCollective"
						class="crumb-button home"
						@click="onClickCollectivesList">
						<template #icon>
							<CollectivesIcon :size="20" />
						</template>
						{{ collectivesCrumbString }}
					</NcButton>
				</div>
				<template v-if="selectedCollective">
					<div class="crumbs-level">
						<ChevronRightIcon :size="20" />
						<NcButton type="tertiary"
							:aria-label="t('collectives', 'Breadcrumb for collective {name}', { name: selectedCollective.name })"
							:disabled="pageCrumbs.length === 0"
							class="crumb-button"
							@click="onClickCollectiveHome">
							<template v-if="selectedCollective.emoji" #icon>
								{{ selectedCollective.emoji }}
							</template>
							{{ selectedCollective.name }}
						</NcButton>
					</div>
					<div v-for="(page, index) in pageCrumbs"
						:key="page.id"
						:aria-label="t('collectives', 'Breadcrumb for page {page}', { page: page.title })"
						class="crumbs-level">
						<ChevronRightIcon :size="20" />
						<NcButton type="tertiary"
							:disabled="(index + 1) === pageCrumbs.length"
							class="crumb-button"
							@click="onClickPage(page)">
							{{ page.title }}
						</NcButton>
					</div>
				</template>
			</span>
			<div class="picker-list">
				<ul v-if="!selectedCollective">
					<li v-for="collective in collectives"
						:id="`picker-collective-${collective.id}`"
						:key="collective.id">
						<a href="#" class="picker-item" @click="onClickCollective(collective)">
							<div v-if="collective.emoji" class="picker-icon">
								{{ collective.emoji }}
							</div>
							<CollectivesIcon v-else
								class="picker-icon"
								:size="20" />
							<div class="picker-title">
								{{ collective.name }}
							</div>
						</a>
					</li>
				</ul>
				<SkeletonLoading v-else-if="loading('pages-foreign-collective')" type="items" />
				<ul v-else-if="subpages.length > 0">
					<li v-for="(page, index) in subpages"
						:id="`picker-page-${page.id}`"
						:key="page.id">
						<a :class="{'self': page.id === pageId}"
							:href="page.id === pageId ? false : '#'"
							class="picker-item"
							@click="onClickPage(page)">
							<div v-if="page.emoji" class="picker-icon">
								{{ page.emoji }}
							</div>
							<PageIcon v-else
								class="picker-icon"
								:size="20"
								fill-color="var(--color-background-darker)" />
							<div class="picker-title">
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
				<NcButton type="secondary"
					:disabled="isActionButtonsDisabled"
					@click="onMoveOrCopy(true)">
					<template #icon>
						<NcLoadingIcon v-if="isCopying" :size="20" />
					</template>
					{{ copyPageString }}
				</NcButton>
				<NcButton type="primary"
					:disabled="isActionButtonsDisabled"
					@click="onMoveOrCopy(false)">
					<template #icon>
						<NcLoadingIcon v-if="isMoving" :size="20" />
					</template>
					{{ movePageString }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { mapGetters, mapMutations, mapState } from 'vuex'
import { NcButton, NcLoadingIcon, NcModal } from '@nextcloud/vue'
import ArrowDownIcon from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUpIcon from 'vue-material-design-icons/ArrowUp.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import PageIcon from '../Icon/PageIcon.vue'
import SkeletonLoading from '../SkeletonLoading.vue'
import { sortedSubpages, pageParents } from '../../store/pageExtracts.js'

export default {
	name: 'PagePicker',

	components: {
		ArrowDownIcon,
		ArrowUpIcon,
		ChevronRightIcon,
		CollectivesIcon,
		NcButton,
		NcLoadingIcon,
		NcModal,
		PageIcon,
		SkeletonLoading,
	},

	props: {
		isCopying: {
			type: Boolean,
			default: false,
		},
		isMoving: {
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
			collectivesPages: {},
			pageCrumbs: [],
			subpages: [],
			selectedCollective: null,
			selectedPageId: null,
		}
	},

	computed: {
		...mapState({
			collectives: (state) => state.collectives.collectives,
		}),

		...mapGetters([
			'currentCollective',
			'rootPage',
			'loading',
			'pageById',
			'pageParents',
			'sortOrder',
			'visibleSubpages',
		]),

		isActionButtonsDisabled() {
			return !this.selectedCollective || this.isCopying || this.isMoving
		},

		isCurrentCollective() {
			return this.selectedCollective?.id === this.currentCollective.id
		},

		selectedRootPage() {
			if (!this.selectedCollective) {
				return null
			}

			return this.isCurrentCollective
				? this.rootPage
				: this.collectivesPages[this.selectedCollective.id]?.find(p => (p.parentId === 0))
		},

		collectivesCrumbString() {
			return this.selectedCollective
				? ''
				: t('collectives', 'All collectives')
		},

		movePageString() {
			return !this.selectedCollective || this.isCurrentCollective
				? t('collectives', 'Move page here')
				: t('collectives', 'Move page to {collective}', { collective: this.selectedCollective.name })
		},

		copyPageString() {
			return !this.selectedCollective || this.isCurrentCollective
				? t('collectives', 'Copy page here')
				: t('collectives', 'Copy page to {collective}', { collective: this.selectedCollective.name })
		},
	},

	watch: {
		'selectedPageId'(val) {
			if (val) {
				this.updateSubpages()
			}
		},
	},

	mounted() {
		this.selectedPageId = this.parentId
		this.selectedCollective = this.currentCollective
		this.updateSubpages()

		window.addEventListener('keydown', this.handleKeyDown, true)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.handleKeyDown, true)
	},

	methods: {
		...mapMutations(['done', 'load']),

		async fetchCollectivePages() {
			this.load('pages-foreign-collective')
			this.subpages = []
			const response = await axios.get(generateUrl(`/apps/collectives/_api/${this.selectedCollective.id}/_pages`))
			this.collectivesPages[this.selectedCollective.id] = response.data.data
			this.done('pages-foreign-collective')
		},

		updateSubpages() {
			if (this.isCurrentCollective) {
				this.subpages = this.visibleSubpages(this.selectedPageId)
			} else {
				const state = { pages: this.collectivesPages[this.selectedCollective.id] }
				const getters = { sortOrder: this.sortOrder }
				this.subpages = sortedSubpages(state, getters)(this.selectedPageId)
			}

			// Add current page to top of subpages if not part of it yet
			if (!this.subpages.find(p => (p.id === this.pageId))) {
				this.subpages.unshift(this.pageById(this.pageId))
			}

			// Scroll current page into view (important when listing parent page)
			this.$nextTick(() => {
				document.getElementById(`picker-page-${this.pageId}`).scrollIntoView({ block: 'center' })
			})

			this.updatePageCrumbs()
		},

		updatePageCrumbs() {
			if (this.isCurrentCollective) {
				this.pageCrumbs = this.pageParents(this.selectedPageId)
			} else {
				const state = { pages: this.collectivesPages[this.selectedCollective.id] }
				const getters = { rootPage: this.selectedRootPage }
				this.pageCrumbs = pageParents(state, getters)(this.selectedPageId)
			}
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

		onClickCollectivesList() {
			this.pageCrumbs = []
			this.subpages = []
			this.selectedCollective = null
			this.selectedPageId = null
		},

		onClickCollectiveHome() {
			this.onClickCollective(this.selectedCollective)
		},

		/**
		 *
		 * @param {object} collective collective object
		 */
		async onClickCollective(collective) {
			this.selectedCollective = collective
			if (!this.isCurrentCollective && !this.collectivesPages[this.selectedCollective.id]) {
				await this.fetchCollectivePages()
			}
			this.selectedPageId = this.selectedRootPage.id
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

		onMoveOrCopy(copy) {
			const args = {
				collectiveId: this.selectedCollective.id,
				parentId: this.selectedPageId,
				newIndex: this.subpages.findIndex(p => p.id === this.pageId),
			}

			if (copy) {
				this.$emit('copy', args)
			} else {
				this.$emit('move', args)
			}
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
		max-width: 300px;

		.crumb-button {
			color: var(--color-text-maxcontrast);

			&.home {
				padding-left: 0;
				font-weight: bold;
			}
		}

		&.crumbs-home {
			flex-shrink: 0;
		}

		&.crumbs-level {
			display: inline-flex;
			min-width: 65px;

			&:last-child {
				flex-shrink: 0;
			}
		}

		&:last-child {
			.crumb-button {
				color: var(--color-main-text);
			}
		}
	}
}

.picker-list {
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

		// Element of the page that is to be copied/moved
		&.self {
			background-color: var(--color-primary-element-light);
		}
	}

	li a.self {
		cursor: default;

		.picker-icon, .picker-title {
			cursor: default;
		}
	}

	.picker-icon {
		display: flex;
		justify-content: center;
		align-items: center;
		width: 44px;
	}

	.picker-title {
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
	gap: 12px;
}
</style>
