<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div :class="[isFullWidthView ? 'full-width-view' : 'sheet-view']">
		<h2 id="titleform" class="page-title">
			<!-- Page emoji or icon -->
			<div class="page-title-icon"
				:class="{ 'mobile': isMobile }">
				<!-- Landing page: collective emoji or CollectivesIcon -->
				<div v-if="isLandingPage && currentCollective.emoji">
					{{ currentCollective.emoji }}
				</div>
				<CollectivesIcon v-else-if="isLandingPage" :size="pageTitleIconSize" fill-color="var(--color-text-maxcontrast)" />
				<PageTemplateIcon v-else-if="isTemplatePage" :size="pageTitleIconSize" fill-color="var(--color-text-maxcontrast)" />

				<!-- Emoji picker if editable -->
				<NcEmojiPicker v-else-if="currentCollectiveCanEdit"
					ref="page-emoji-picker"
					:show-preview="true"
					:allow-unselect="true"
					:selected-emoji="currentPage.emoji"
					@select="onSelectEmoji"
					@unselect="onUnselectEmoji">
					<NcButton type="tertiary"
						:aria-label="t('collectives', 'Select emoji for page')"
						:title="t('collectives', 'Select emoji')"
						class="button-emoji-page"
						:class="{ 'mobile': isMobile }"
						@click.prevent>
						<template #icon>
							<NcLoadingIcon v-if="emojiButtonIsLoading"
								:size="pageTitleIconSize"
								fill-color="var(--color-text-maxcontrast)" />
							<div v-else-if="currentPage.emoji">
								{{ currentPage.emoji }}
							</div>
							<EmoticonOutlineIcon v-else
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
					<EmoticonOutlineIcon v-else
						class="emoji-picker-emoticon"
						:size="pageTitleIconSize"
						fill-color="var(--color-text-maxcontrast)" />
				</template>
			</div>

			<!-- Page title -->
			<form @submit.prevent="focusEditor()">
				<input v-if="isLandingPage"
					ref="landingPageTitle"
					:title="titleIfTruncated(currentCollective.name)"
					class="title"
					:class="{ 'mobile': isMobile }"
					type="text"
					disabled
					:value="currentCollective.name">
				<input v-else-if="isTemplatePage"
					class="title"
					:class="{ 'mobile': isMobile }"
					type="text"
					disabled
					:value="t('collectives', 'Template')">
				<input v-else
					ref="title"
					v-model="newTitle"
					:title="titleIfTruncated(newTitle)"
					class="title"
					:class="{ 'mobile': isMobile }"
					:placeholder="t('collectives', 'Title')"
					type="text"
					:disabled="!currentCollectiveCanEdit"
					@blur="onTitleBlur()"
					@keydown.stop="onTitleKeyDown">
			</form>

			<div class="titlebar-buttons" :class="{'titlebar-buttons_sidebar-toggle': !isMobile && !showing('sidebar')}">
				<!-- Edit button if editable -->
				<EditButton v-if="currentCollectiveCanEdit"
					:mobile="isMobile"
					class="edit-button" />

				<!-- Actions menu -->
				<PageActionMenu :show-files-link="!isPublic"
					:page-id="currentPage.id"
					:parent-id="currentPage.parentId"
					:timestamp="currentPage.timestamp"
					:last-user-id="currentPage.lastUserId"
					:last-user-display-name="currentPage.lastUserDisplayName"
					:is-landing-page="isLandingPage"
					:is-template="isTemplatePage" />
			</div>
		</h2>
		<LandingPageWidgets v-if="isLandingPage" />
		<TextEditor :key="`text-editor-${currentPage.id}`" ref="texteditor" />
		<SearchDialog />
	</div>
</template>

<script>
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import CollectivesIcon from './Icon/CollectivesIcon.vue'
import EmoticonOutlineIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import EditButton from './Page/EditButton.vue'
import LandingPageWidgets from './Page/LandingPageWidgets.vue'
import PageActionMenu from './Page/PageActionMenu.vue'
import PageTemplateIcon from './Icon/PageTemplateIcon.vue'
import TextEditor from './Page/TextEditor.vue'
import SearchDialog from './SearchDialog.vue'
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import pageMixin from '../mixins/pageMixin.js'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'Page',

	components: {
		CollectivesIcon,
		EditButton,
		EmoticonOutlineIcon,
		LandingPageWidgets,
		NcButton,
		NcEmojiPicker,
		NcLoadingIcon,
		PageActionMenu,
		PageTemplateIcon,
		TextEditor,
		SearchDialog,
	},

	mixins: [
		isMobile,
		pageMixin,
	],

	data() {
		return {
			newTitle: '',
			titleIsTruncated: false,
		}
	},

	computed: {
		...mapState(useRootStore, [
			'isPublic',
			'isTextEdit',
			'loading',
			'showing',
		]),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectiveCanEdit',
		]),
		...mapState(usePagesStore, [
			'currentPage',
			'isIndexPage',
			'isFullWidthView',
			'isTemplatePage',
			'isLandingPage',
		]),

		hasSidebarToggle() {
			return !this.showing('sidebar')
		},

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
				filePath.split('/').forEach(part => part && parts.unshift(part))
				if (!this.isIndexPage) {
					parts.unshift(title)
				}
			}
			return parts.join(' - ')
		},

		titleIfTruncated() {
			return (title) => this.titleIsTruncated ? title : null
		},

		emojiButtonIsLoading() {
			return this.loading(`pageEmoji-${this.currentPage.id}`)
		},

		showingPageEmojiPicker() {
			return this.showing('pageEmojiPicker')
		},

		pageTitleIconSize() {
			return isMobile ? 25 : 30
		},
	},

	watch: {
		'documentTitle'() {
			document.title = this.documentTitle
		},

		'newTitle'() {
			this.$nextTick(() => {
				if (this.$refs.title) {
					this.titleIsTruncated = this.$refs.title.scrollWidth > this.$refs.title.clientWidth

				} else if (this.$refs.landingPageTitle) {
					this.titleIsTruncated = this.$refs.landingPageTitle.scrollWidth > this.$refs.landingPageTitle.clientWidth
				}
			})
		},

		'showingPageEmojiPicker'(val) {
			if (val === true) {
				this.openPageEmojiPicker()
			}
		},

		'currentPage.id'() {
			this.initTitleEntry()
			this.hide('outline')
		},
	},

	mounted() {
		this.initFullWidthPageIds()
		document.title = this.documentTitle
		this.initTitleEntry()
	},

	methods: {
		...mapActions(useRootStore, [
			'done',
			'hide',
			'load',
		]),
		...mapActions(usePagesStore, [
			'getPages',
			'renamePage',
			'initFullWidthPageIds',
		]),

		initTitleEntry() {
			if (this.loading('newPageTitle')) {
				this.newTitle = ''
				this.$nextTick(this.focusTitle)
				this.done('newPageTitle')
				return
			}
			this.newTitle = this.currentPage.title
		},

		focusTitle() {
			this.$refs.title.focus()
		},

		focusEditor() {
			this.$refs.texteditor.focusEditor()
		},

		saveEditor() {
			this.$refs.texteditor.save()
		},

		async onSelectEmoji(emoji) {
			await this.setEmoji(this.currentPage.id, emoji)
		},

		onUnselectEmoji() {
			return this.onSelectEmoji('')
		},

		openPageEmojiPicker() {
			this.$refs['page-emoji-picker'].open = true
			this.hide('pageEmojiPicker')
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
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename the page'))
			}
		},

		onTitleKeyDown(event) {
			if (this.isTextEdit && (event.ctrlKey || event.metaKey) && event.key === 's') {
				this.saveEditor()
				event.preventDefault()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
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
@import '../css/editor';

@media print {
	/* Don't print emoticon button (if page doesn't have an emoji set) */
	.edit-button, .action-item, .emoji-picker-emoticon {
		display: none !important;
	}
}
</style>
