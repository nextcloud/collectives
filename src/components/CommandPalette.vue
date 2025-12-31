<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		v-if="isOpen"
		:name="t('collectives', 'Command Palette')"
		size="normal"
		class="command-palette"
		@closing="close">
		<div class="command-palette__content">
			<div class="command-palette__search">
				<NcTextField
					ref="searchInput"
					v-model="searchQuery"
					:label="t('collectives', 'Search pages, collectives, or commands…')"
					:placeholder="t('collectives', 'Type to search…')"
					trailing-button-icon="close"
					:show-trailing-button="searchQuery.length > 0"
					@trailing-button-click="searchQuery = ''">
					<MagnifyIcon :size="20" />
				</NcTextField>
			</div>

			<div class="command-palette__results">
				<ul
					v-if="filteredItems.length > 0"
					ref="resultsList"
					class="command-palette__list">
					<li
						v-for="(item, index) in filteredItems"
						:key="item.id"
						:class="{ 'command-palette__item--selected': index === selectedIndex }"
						class="command-palette__item"
						@click="executeItem(item)"
						@mouseenter="selectedIndex = index">
						<div class="command-palette__item-icon">
							<component
								:is="item.icon"
								v-if="item.icon"
								:size="20" />
							<span v-else-if="item.emoji" class="command-palette__item-emoji">
								{{ item.emoji }}
							</span>
						</div>
						<div class="command-palette__item-content">
							<div class="command-palette__item-title">
								{{ item.title }}
							</div>
							<div v-if="item.subtitle" class="command-palette__item-subtitle">
								{{ item.subtitle }}
							</div>
						</div>
						<div v-if="item.badge" class="command-palette__item-badge">
							{{ item.badge }}
						</div>
					</li>
				</ul>
				<NcEmptyContent
					v-else
					:name="t('collectives', 'No results found')"
					:description="t('collectives', 'Try a different search term')">
					<template #icon>
						<MagnifyIcon />
					</template>
				</NcEmptyContent>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { NcDialog, NcEmptyContent, NcTextField } from '@nextcloud/vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router/composables'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import { useCommandPaletteActions } from '../composables/useCommandPaletteActions.js'
import { useCommandPaletteCommands } from '../composables/useCommandPaletteCommands.js'
import { useCommandPaletteSearch } from '../composables/useCommandPaletteSearch.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { useCommandPaletteStore } from '../stores/commandPalette.js'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'

export default {
	name: 'CommandPalette',

	components: {
		NcDialog,
		NcEmptyContent,
		NcTextField,
		MagnifyIcon,
	},

	setup() {
		const router = useRouter()
		const commandPaletteStore = useCommandPaletteStore()
		const collectivesStore = useCollectivesStore()
		const pagesStore = usePagesStore()
		const rootStore = useRootStore()

		// Reactive state
		const searchQuery = ref('')
		const selectedIndex = ref(0)
		const searchInput = ref(null)
		const resultsList = ref(null)

		// Setup composables
		const context = {
			router,
			t: window.t,
			isPublic: computed(() => rootStore.isPublic),
			collectives: computed(() => collectivesStore.collectives),
			pages: computed(() => pagesStore.pages),
			allPages: computed(() => pagesStore.allPages),
			currentCollective: computed(() => collectivesStore.currentCollective),
			currentPage: computed(() => pagesStore.currentPage),
			currentPageDavUrl: computed(() => pagesStore.currentPageDavUrl),
			rootPage: computed(() => pagesStore.rootPage),
			currentCollectiveCanEdit: computed(() => collectivesStore.currentCollectiveCanEdit),
			currentCollectiveCanShare: computed(() => collectivesStore.currentCollectiveCanShare),
			isTextEdit: computed(() => pagesStore.isTextEdit),
			hasOutline: computed(() => pagesStore.hasOutline),
			hasSubpages: computed(() => pagesStore.hasSubpages),
			isFavoritePage: computed(() => collectivesStore.isFavoritePage),
			pagePath: pagesStore.pagePath,
			collectivePath: collectivesStore.collectivePath,
			pagesForCollective: computed(() => pagesStore.pagesForCollective),
			setTextEdit: pagesStore.setTextEdit,
			setTextView: pagesStore.setTextView,
			toggleOutline: pagesStore.toggleOutline,
			setNewPageParentId: pagesStore.setNewPageParentId,
			setFullWidthView: pagesStore.setFullWidthView,
			trashPage: pagesStore.trashPage,
			toggleFavoritePage: collectivesStore.toggleFavoritePage,
			show: rootStore.show,
			setActiveSidebarTab: rootStore.setActiveSidebarTab,
		}

		const actions = useCommandPaletteActions(context)
		const commandsComposable = useCommandPaletteCommands({ ...context, actions })
		const searchComposable = useCommandPaletteSearch({ ...context, actions })

		const filteredItems = computed(() => {
			const items = []
			const query = searchQuery.value.toLowerCase().trim()

			items.push(...commandsComposable.getCommands(query))

			if (!rootStore.isPublic) {
				items.push(...searchComposable.getCollectives(query))
			}

			if (collectivesStore.currentCollective) {
				items.push(...searchComposable.getPages(query))
			}

			items.sort((a, b) => (a.title.toLowerCase() === query) - (b.title.toLowerCase() === query) || 0)

			return items
		})

		const scrollToSelected = () => {
			nextTick(() => {
				const list = resultsList.value
				const items = list?.querySelectorAll('.command-palette__item')
				const selectedItem = items?.[selectedIndex.value]

				if (selectedItem && list) {
					const listRect = list.getBoundingClientRect()
					const itemRect = selectedItem.getBoundingClientRect()

					if (itemRect.bottom > listRect.bottom) {
						selectedItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
					} else if (itemRect.top < listRect.top) {
						selectedItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
					}
				}
			})
		}

		const executeItem = async (item) => {
			if (!item?.action) {
				return
			}

			try {
				await Promise.resolve(item.action())
				commandPaletteStore.close()
			} catch (e) {
				console.error(e)
				showError(window.t('collectives', 'Command failed'))
			}
		}

		const handleKeyDown = (event) => {
			if (!commandPaletteStore.isOpen) {
				return
			}

			switch (event.key) {
				case 'ArrowDown':
					event.preventDefault()
					selectedIndex.value = Math.min(
						selectedIndex.value + 1,
						filteredItems.value.length - 1,
					)
					scrollToSelected()
					break

				case 'ArrowUp':
					event.preventDefault()
					selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
					scrollToSelected()
					break

				case 'Enter':
					event.preventDefault()
					if (filteredItems.value[selectedIndex.value]) {
						executeItem(filteredItems.value[selectedIndex.value])
					}
					break

				case 'Escape':
					event.preventDefault()
					commandPaletteStore.close()
					break
			}
		}

		watch(() => commandPaletteStore.isOpen, (newVal) => {
			if (newVal) {
				searchQuery.value = ''
				selectedIndex.value = 0
				nextTick(() => {
					searchInput.value?.$el?.querySelector('input')?.focus()
				})
			}
		})

		watch(searchQuery, () => {
			selectedIndex.value = 0
		})

		watch(filteredItems, () => {
			if (selectedIndex.value >= filteredItems.value.length) {
				selectedIndex.value = Math.max(0, filteredItems.value.length - 1)
			}
		})

		onMounted(() => {
			document.addEventListener('keydown', handleKeyDown)
		})

		onBeforeUnmount(() => {
			document.removeEventListener('keydown', handleKeyDown)
		})

		return {
			searchQuery,
			selectedIndex,
			searchInput,
			resultsList,

			isOpen: computed(() => commandPaletteStore.isOpen),
			filteredItems,

			close: commandPaletteStore.close,
			executeItem,
		}
	},
}
</script>

<style scoped lang="scss">
.command-palette {
	:deep(.modal-container) {
		height: 400px;
		max-height: min(100vh, 400px);
	}

	:deep(.dialog__name) {
		display: none;
	}

	:deep(.modal-wrapper .modal-container__content) {
		padding: 0;
		overflow: hidden;
	}

	&__content {
		display: flex;
		flex-direction: column;
		height: 100%;
	}

	&__search {
		padding: calc(var(--default-grid-baseline) * 4);
		border-bottom: 1px solid var(--color-border);
		flex-shrink: 0;
	}

	&__results {
		flex: 1;
		overflow-y: auto;
	}

	&__list {
		list-style: none;
		margin: 0;
		padding: calc(var(--default-grid-baseline) * 2) 0;
	}

	&__item {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 3);
		padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4);
		cursor: pointer;
		transition: background-color 0.1s ease;

		&:hover,
		&--selected {
			background-color: var(--color-background-hover);
		}

		&-icon {
			flex-shrink: 0;
			width: 32px;
			height: 32px;
			display: flex;
			align-items: center;
			justify-content: center;
			background-color: var(--color-background-dark);
			border-radius: var(--border-radius-large);
		}

		&-emoji {
			font-size: 20px;
			line-height: 1;
		}

		&-content {
			flex: 1;
			min-width: 0;
			overflow: hidden;
		}

		&-title {
			font-weight: 500;
			color: var(--color-main-text);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&-subtitle {
			font-size: 12px;
			color: var(--color-text-maxcontrast);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&-badge {
			flex-shrink: 0;
			padding: 2px 8px;
			border-radius: var(--border-radius-pill);
			background-color: var(--color-primary-element-light);
			color: var(--color-primary-element-text);
			font-size: 11px;
			font-weight: 500;
		}
	}
}
</style>
