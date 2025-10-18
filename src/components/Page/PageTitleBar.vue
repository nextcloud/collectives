<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="page-title-container"
		:class="{
			'full-width-view': isFullWidth,
			'sheet-view': !isFullWidth,
		}"
		data-cy-collectives="page-title-container">
		<!-- Page emoji or icon -->
		<div
			class="page-title-icon"
			:class="{ mobile: isMobile }">
			<!-- Landing page: collective emoji or CollectivesIcon -->
			<div v-if="isLandingPage && currentCollective.emoji">
				{{ currentCollective.emoji }}
			</div>
			<CollectivesIcon v-else-if="isLandingPage" :size="pageTitleIconSize" fill-color="var(--color-text-maxcontrast)" />

			<!-- Emoji picker if editable -->
			<NcEmojiPicker
				v-else-if="currentCollectiveCanEdit"
				ref="page-emoji-picker"
				:show-preview="true"
				:allow-unselect="true"
				:selected-emoji="currentPage.emoji"
				@select="onSelectEmoji"
				@unselect="onUnselectEmoji">
				<NcButton
					variant="tertiary"
					:aria-label="t('collectives', 'Select emoji for page')"
					:title="t('collectives', 'Select emoji')"
					class="button-emoji-page"
					:class="{ mobile: isMobile }"
					:disabled="!networkOnline"
					@click.prevent>
					<template #icon>
						<NcLoadingIcon
							v-if="emojiButtonIsLoading"
							:size="pageTitleIconSize"
							fill-color="var(--color-text-maxcontrast)" />
						<div v-else-if="currentPage.emoji">
							{{ currentPage.emoji }}
						</div>
						<EmoticonIcon
							v-else
							class="emoji-picker-emoticon"
							:size="pageTitleIconSize"
							fill-color="var(--color-text-maxcontrast)" />
					</template>
				</NcButton>
			</NcEmojiPicker>

			<!-- Page emoji or PageIcon if not editable -->
			<template v-else>
				<div v-if="currentPage.emoji">
					{{ currentPage.emoji }}
				</div>
				<EmoticonIcon
					v-else
					class="emoji-picker-emoticon"
					:size="pageTitleIconSize"
					fill-color="var(--color-text-maxcontrast)" />
			</template>
		</div>

		<!-- Page title -->
		<PageTitle
			v-if="isLandingPage"
			ref="pageTitle"
			:value="currentCollective.name"
			:disabled="true" />
		<PageTitle
			v-else
			ref="pageTitle"
			v-model="newTitle"
			:placeholder="t('collectives', 'Title')"
			:disabled="!currentCollectiveCanEdit || !networkOnline"
			@blur="onTitleBlur()"
			@save="$emit('save-editor')"
			@submit="onSubmit()" />

		<div class="titlebar-buttons" :class="{ 'titlebar-buttons_sidebar-toggle': !isMobile && !showing('sidebar') }">
			<!-- Edit button if editable -->
			<EditButton
				v-if="currentCollectiveCanEdit"
				:mobile="isMobile"
				class="edit-button" />

			<!-- Actions menu -->
			<PageActionMenu
				:page-id="currentPage.id"
				:parent-id="currentPage.parentId"
				:timestamp="currentPage.timestamp"
				:last-user-id="currentPage.lastUserId"
				:last-user-display-name="currentPage.lastUserDisplayName"
				:is-landing-page="isLandingPage"
				:network-online="networkOnline" />
		</div>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { NcButton, NcEmojiPicker, NcLoadingIcon } from '@nextcloud/vue'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import EmoticonIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import EditButton from './EditButton.vue'
import PageActionMenu from './PageActionMenu.vue'
import PageTitle from './PageTitle.vue'
import { useNetworkState } from '../../composables/useNetworkState.js'
import pageMixin from '../../mixins/pageMixin.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'PageTitleBar',

	components: {
		CollectivesIcon,
		EditButton,
		EmoticonIcon,
		NcButton,
		NcEmojiPicker,
		NcLoadingIcon,
		PageActionMenu,
		PageTitle,
	},

	mixins: [
		pageMixin,
	],

	props: {
		isFullWidth: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const isMobile = useIsMobile()
		const { networkOnline } = useNetworkState()
		return { isMobile, networkOnline }
	},

	data() {
		return {
			newTitle: '',
		}
	},

	computed: {
		...mapState(useRootStore, ['loading', 'showing']),

		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectiveCanEdit',
		]),

		...mapState(usePagesStore, [
			'currentPage',
			'currentPagePath',
			'isIndexPage',
			'isLandingPage',
			'isTextEdit',
		]),

		titleChanged() {
			return this.newTitle && this.newTitle !== this.currentPage.title
		},

		documentTitle() {
			const { filePath, title } = this.currentPage
			const parts = [
				this.currentCollective.name,
				t('collectives', 'Collectives'),
				'Nextcloud',
			]
			if (!this.isLandingPage) {
				// Add parent page names in reverse order
				filePath.split('/').forEach((part) => part && parts.unshift(part))
				if (!this.isIndexPage) {
					parts.unshift(title)
				}
			}
			return parts.join(' - ')
		},

		emojiButtonIsLoading() {
			return this.loading(`pageEmoji-${this.currentPage.id}`)
		},

		pageTitleIconSize() {
			return this.isMobile ? 25 : 30
		},
	},

	watch: {
		documentTitle: function() {
			document.title = this.documentTitle
		},

		'currentPage.id': function() {
			this.initTitleEntry()
		},
	},

	mounted() {
		document.title = this.documentTitle
		this.initTitleEntry()

		subscribe('collectives:page:open-emoji-picker', this.openPageEmojiPicker)
	},

	beforeDestroy() {
		unsubscribe('collectives:page:open-emoji-picker', this.openPageEmojiPicker)
	},

	methods: {
		...mapActions(useRootStore, ['done']),

		...mapActions(usePagesStore, [
			'getPages',
			'renamePage',
		]),

		initTitleEntry() {
			if (this.loading('newPageTitle')) {
				this.newTitle = ''
				this.$nextTick(() => {
					// Delay focus to prevent focus being stolen via race condition
					setTimeout(() => {
						this.$refs.pageTitle.focus()
					}, 50)
				})
				this.done('newPageTitle')
				return
			}
			this.newTitle = this.currentPage.title
		},

		async onSelectEmoji(emoji) {
			await this.setEmoji(this.currentPage.id, emoji)
		},

		onUnselectEmoji() {
			return this.onSelectEmoji('')
		},

		openPageEmojiPicker() {
			this.$refs['page-emoji-picker'].open = true
		},

		/**
		 * Rename currentPage on the server
		 */
		async onTitleBlur() {
			if (!this.titleChanged) {
				return
			}
			try {
				await this.renamePage(this.newTitle)
				// The resulting title may be different due to sanitizing
				this.newTitle = this.currentPage.title
				this.getPages(false)
				await this.$router.replace(this.currentPagePath)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename the page'))
			}
		},

		async onSubmit() {
			if (this.isTextEdit) {
				this.$emit('focus-editor')
			} else {
				await this.onTitleBlur()
			}
		},
	},
}
</script>

<style scoped lang="scss">
.page-title-container {
	display: flex;
	max-width: 100%;
	min-height: 48px;
	padding: 0 8px;
	align-items: center;
	background-color: var(--color-main-background);

	&.sheet-view {
		margin: 0 0 0 max(0px, calc(50% - (var(--text-editor-max-width) / 2)));
	}

	.button-emoji-page {
		font-size: 0.8em;
	}
}

/* Leave space for page list toggle on small screens (editor width 80ch + toggle button 44px) */
@media screen and (max-width: calc(80ch + 44px)) {
	.page-title-container {
		padding-left: calc(var(--default-clickable-area) + 4px);
	}
}

.page-title-icon {
	font-size: 1.8em;
}

.titlebar-buttons {
	display: flex;
	gap: 4px;
	align-items: center;

	&_sidebar-toggle {
		margin-right: calc(var(--default-clickable-area) + 2px);
	}
}
</style>

<style lang="scss">
@media print {
	/* Don't print emoticon button (if page doesn't have an emoji set) */
	.edit-button, .action-item, .emoji-picker-emoticon {
		display: none !important;
	}
}
</style>
