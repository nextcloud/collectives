<template>
	<div>
		<h1 id="titleform" class="page-title">
			<div class="page-title-icon">
				<div v-if="landingPage && currentCollective.emoji">
					{{ currentCollective.emoji }}
				</div>
				<CollectivesIcon v-else-if="landingPage" :size="30" fill-color="var(--color-text-maxcontrast)" />
				<PageTemplateIcon v-else-if="isTemplatePage" :size="30" fill-color="var(--color-text-maxcontrast)" />
				<NcEmojiPicker v-else
					ref="page-emoji-picker"
					:show-preview="true"
					@select="setPageEmoji">
					<NcButton type="tertiary"
						:aria-label="t('collectives', 'Select emoji for page')"
						:title="t('collectives', 'Select emoji')"
						class="button-emoji-page"
						@click.prevent>
						<template #icon>
							<LoadingIcon v-if="emojiButtonIsLoading"
								class="animation-rotate"
								:size="30"
								fill-color="var(--color-text-maxcontrast)" />
							<div v-else-if="currentPage.emoji">
								{{ currentPage.emoji }}
							</div>
							<EmoticonOutlineIcon v-else
								class="emoji-picker-emoticon"
								:size="30"
								fill-color="var(--color-text-maxcontrast)" />
						</template>
					</NcButton>
				</NcEmojiPicker>
			</div>
			<form @submit.prevent="startEdit()">
				<input v-if="landingPage"
					ref="landingPageTitle"
					v-tooltip="titleIfTruncated(currentCollective.name)"
					class="title"
					type="text"
					disabled
					:value="currentCollective.name">
				<input v-else-if="isTemplatePage"
					class="title"
					type="text"
					disabled
					:value="t('collectives', 'Template')">
				<input v-else
					ref="title"
					v-model="newTitle"
					v-tooltip="titleIfTruncated(newTitle)"
					class="title"
					:placeholder="t('collectives', 'Title')"
					type="text"
					:disabled="!currentCollectiveCanEdit"
					@blur="renamePage()">
			</form>
			<EditButton v-if="currentCollectiveCanEdit"
				:edit-mode-and-ready="editMode && !waitForEditor"
				:loading="titleFormButtonIsLoading"
				:mobile="isMobile"
				@click="editMode ? stopEdit() : startEdit()" />
			<PageActionMenu v-if="currentCollectiveCanEdit"
				:show-files-link="true"
				:page-id="currentPage.id"
				:parent-id="currentPage.parentId"
				:timestamp="currentPage.timestamp"
				:last-user-id="currentPage.lastUserId"
				:is-landing-page="landingPage"
				:is-template="isTemplatePage" />
			<NcActions v-if="!showing('sidebar')">
				<NcActionButton icon="icon-menu-sidebar"
					:aria-label="t('collectives', 'Toggle page sidebar')"
					aria-controls="app-sidebar-vue"
					:close-after-click="true"
					@click="toggle('sidebar')" />
			</NcActions>
		</h1>
		<div v-show="showRichText"
			id="text-container"
			:key="'text-' + currentPage.id"
			:aria-label="t('collectives', 'Page content')">
			<RichText :key="`show-${currentPage.id}`"
				:as-placeholder="waitForEditor"
				:current-page="currentPage"
				:page-content="pageContent" />
		</div>
		<Editor v-if="currentCollectiveCanEdit"
			v-show="showEditor"
			:key="`edit-${currentPage.id}`"
			ref="editor"
			@ready="readyEditor" />
	</div>
</template>

<script>
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { NcActions, NcActionButton, NcButton } from '@nextcloud/vue'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import CollectivesIcon from './Icon/CollectivesIcon.vue'
import EmoticonOutlineIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import LoadingIcon from 'vue-material-design-icons/Loading.vue'
import EditButton from './Page/EditButton.vue'
import Editor from './Page/Editor.vue'
import RichText from './Page/RichText.vue'
import PageActionMenu from './Page/PageActionMenu.vue'
import PageTemplateIcon from './Icon/PageTemplateIcon.vue'
import { showError } from '@nextcloud/dialogs'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import {
	RENAME_PAGE,
	TOUCH_PAGE,
	GET_PAGES,
	GET_VERSIONS,
} from '../store/actions.js'
import pageMixin from '../mixins/pageMixin.js'
import pageContentMixin from '../mixins/pageContentMixin.js'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'Page',

	components: {
		NcActionButton,
		NcActions,
		NcButton,
		NcEmojiPicker,
		CollectivesIcon,
		EditButton,
		Editor,
		EmoticonOutlineIcon,
		LoadingIcon,
		PageActionMenu,
		PageTemplateIcon,
		RichText,
	},

	mixins: [
		isMobile,
		pageMixin,
		pageContentMixin,
	],

	data() {
		return {
			previousSaveTimestamp: null,
			readMode: true,
			newTitle: '',
			editToggle: EditState.Unset,
			scrollTop: 0,
			pageContent: '',
			titleIsTruncated: false,
		}
	},

	computed: {
		...mapGetters([
			'currentPage',
			'currentPageDavUrl',
			'currentCollective',
			'currentCollectiveCanEdit',
			'hasVersionsLoaded',
			'indexPage',
			'isPublic',
			'isTemplatePage',
			'landingPage',
			'loading',
			'pageParam',
			'showing',
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
			if (!this.landingPage) {
				if (this.indexPage) {
					parts.unshift(filePath || title)
				} else {
					parts.unshift(filePath ? filePath + '/' + title : title)
				}
			}
			return parts.join(' - ')
		},

		showRichText() {
			return this.readOnly
		},

		showEditor() {
			return !this.readOnly
		},

		waitForEditor() {
			return this.readMode && this.editMode
		},

		readOnly() {
			return !this.currentCollectiveCanEdit || this.readMode || !this.editMode
		},

		editMode: {
			get() {
				return this.editToggle === EditState.Edit
			},
			set(val) {
				this.editToggle = val ? EditState.Edit : EditState.Read
			},
		},

		titleIfTruncated() {
			return (title) => this.titleIsTruncated ? title : null
		},

		emojiButtonIsLoading() {
			return this.loading(`pageEmoji-${this.currentPage.id}`)
		},

		titleFormButtonIsLoading() {
			return this.loading('pageUpdate') || this.waitForEditor
		},

		showingPageEmojiPicker() {
			return this.showing('pageEmojiPicker')
		},
	},

	watch: {
		'pageParam'() {
			this.initTitleEntry()
		},
		'currentPage.id'() {
			this.editToggle = EditState.Unset
			this.getPageContent()
			this.scrollTop = 0
		},
		'currentPage.timestamp'() {
			if (this.currentPage.timestamp > this.previousSaveTimestamp) {
				this.getPageContent()
			}
		},
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
	},

	mounted() {
		document.title = this.documentTitle
		this.initTitleEntry()
		this.getPageContent()
	},

	methods: {
		...mapMutations([
			'done',
			'hide',
			'load',
			'toggle',
		]),

		...mapActions({
			dispatchRenamePage: RENAME_PAGE,
			dispatchTouchPage: TOUCH_PAGE,
			dispatchGetPages: GET_PAGES,
			dispatchGetVersions: GET_VERSIONS,
		}),

		// this is a method so it does not get cached
		syncService() {
			// `$syncService` in Nexcloud 24+, `syncService` beforehands
			return this.wrapper()?.$syncService ?? this.wrapper()?.syncService
		},

		// this is a method so it does not get cached
		doc() {
			return this.wrapper()?.$data.document
		},

		// this is a method so it does not get cached
		wrapper() {
			return this.$refs.editor?.$children[0].$children[0]
		},

		initTitleEntry() {
			if (this.loading('newPage')) {
				this.newTitle = ''
				// Older versions of text do not pass on the autofocus prop.
				// Only focus the title if the editor won't steal the focus.
				if (!this.wrapper().autofocus) {
					this.$nextTick(this.focusTitle)
				}
				return
			} else if (this.loading('newTemplate')) {
				this.$nextTick(this.focusEditor)
				this.done('newTemplate')
			}
			this.newTitle = this.currentPage.title
		},

		focusTitle() {
			this.$refs.title.focus()
		},

		focusEditor() {
			// `$editor` in Nexcloud 24+, `editor` beforehands
			if (this.wrapper()?.$editor) {
				this.wrapper()?.$editor.commands.focus()
			} else if (this.wrapper()?.tiptap) {
				this.wrapper()?.tiptap.focus()
			} else {
				this.$el.querySelector('.ProseMirror')?.focus()
			}
		},

		/**
		 * Set readMode to false
		 */
		readyEditor() {
			// Set pageContent if it's been empty before
			if (!this.pageContent) {
				this.pageContent = this.syncService()._getContent()
			}
			this.readMode = false
			if (this.loading('newPage')) {
				// Don't steal the focus from title if a new page
				this.done('newPage')
				return
			}
			if (this.editMode) {
				if (this.doc()) {
					this.previousSaveTimestamp = this.doc().lastSavedVersionTime
				}
				this.$nextTick(this.focusEditor())
			}
		},

		/**
		 * Show editor if empty content
		 */
		emptyContent() {
			if (this.editToggle === EditState.Unset) {
				this.startEdit()
			}
		},

		startEdit() {
			this.scrollTop = document.getElementById('text')?.scrollTop || 0
			if (this.doc()) {
				this.previousSaveTimestamp = this.doc().lastSavedVersionTime
			}
			this.editMode = true
			this.$nextTick(() => {
				if (this.scrollTop === 0) {
					this.focusEditor()
				}
				document.getElementById('editor')?.scrollTo(0, this.scrollTop)
			})
		},

		async stopEdit() {
			this.renamePage()

			this.scrollTop = document.getElementById('editor')?.scrollTop || 0

			const pageContent = this.syncService()._getContent()
			const changed = this.pageContent !== pageContent

			// if there is still no page content we remind the user
			if (!pageContent) {
				this.focusEditor()
				return
			}

			if (changed) {
				this.dispatchTouchPage()
				if (!this.isPublic && this.hasVersionsLoaded) {
					this.dispatchGetVersions(this.currentPage.id)
				}

				// Save pending changes in editor
				// TODO: detect missing connection and display warning
				this.syncService().save()

				this.pageContent = pageContent
			}
			this.editMode = false

			this.$nextTick(() => {
				document.getElementById('text')?.scrollTo(0, this.scrollTop)
			})
		},

		/**
		 * Rename currentPage on the server
		 */
		async renamePage() {
			if (!this.titleChanged) {
				return
			}
			try {
				await this.dispatchRenamePage(this.newTitle)
				// The resulting title may be different due to sanitizing
				this.newTitle = this.currentPage.title
				this.dispatchGetPages()
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename the page'))
			}
		},

		async getPageContent() {
			this.pageContent = await this.fetchPageContent(this.currentPageDavUrl)
			if (!this.pageContent) {
				this.emptyContent()
			}
		},

		async setPageEmoji(emoji) {
			await this.setEmoji(this.currentPage.parentId, this.currentPage.id, emoji)
		},

		openPageEmojiPicker() {
			this.$refs['page-emoji-picker'].open = true
			this.hide('pageEmojiPicker')
		},
	},
}
</script>

<style lang="scss" scoped>
#titleform {
	z-index: 10022;

	form {
		flex: auto;
	}
}

#text-container {
	display: block;
	width: 100%;
	max-width: 100%;
	left: 0;
	margin: 0 auto;
	background-color: var(--color-main-background);
}

::v-deep [data-text-el='editor-container'] div.editor {
	/* Adjust to page titlebar height */
	div.text-menubar {
		margin: auto;
		top: 59px;
	}
}

@media print {
	/* Don't print emoticon button (if page doesn't have an emoji set) */
	.titleform-button, .action-item, .emoji-picker-emoticon {
		display: none !important;
	}
}
</style>
