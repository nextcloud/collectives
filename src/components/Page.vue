<template>
	<div>
		<h1 id="titleform" class="page-title">
			<div class="page-title-icon"
				:class="{ 'mobile': isMobile }">
				<div v-if="landingPage && currentCollective.emoji">
					{{ currentCollective.emoji }}
				</div>
				<CollectivesIcon v-else-if="landingPage" :size="pageTitleIconSize" fill-color="var(--color-text-maxcontrast)" />
				<PageTemplateIcon v-else-if="isTemplatePage" :size="pageTitleIconSize" fill-color="var(--color-text-maxcontrast)" />
				<NcEmojiPicker v-else
					ref="page-emoji-picker"
					:show-preview="true"
					@select="setPageEmoji">
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
			</div>
			<form @submit.prevent="focusEditor()">
				<input v-if="landingPage"
					ref="landingPageTitle"
					v-tooltip="titleIfTruncated(currentCollective.name)"
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
					v-tooltip="titleIfTruncated(newTitle)"
					class="title"
					:class="{ 'mobile': isMobile }"
					:placeholder="t('collectives', 'Title')"
					type="text"
					:disabled="!currentCollectiveCanEdit"
					@blur="renamePage()">
			</form>
			<EditButton v-if="currentCollectiveCanEdit"
				:edit-mode="editMode"
				:loading="titleFormButtonIsLoading"
				:mobile="isMobile"
				class="edit-button"
				@click="editMode ? stopEdit() : startEdit()" />
			<PageActionMenu v-if="currentCollectiveCanEdit"
				:show-files-link="!isPublic"
				:page-id="currentPage.id"
				:parent-id="currentPage.parentId"
				:timestamp="currentPage.timestamp"
				:last-user-id="currentPage.lastUserId"
				:last-user-display-name="currentPage.lastUserDisplayName"
				:is-landing-page="landingPage"
				:is-template="isTemplatePage" />
			<NcActions v-if="!showing('sidebar') && !isMobile">
				<NcActionButton icon="icon-menu-sidebar"
					:aria-label="t('collectives', 'Open page sidebar')"
					aria-controls="app-sidebar-vue"
					:close-after-click="true"
					@click="toggle('sidebar')" />
			</NcActions>
		</h1>
		<TextEditor :key="`text-editor-${currentPage.id}`"
			ref="texteditor"
			:edit-mode="editMode"
			@ready="readyEditor"
			@start-edit="startEdit" />
	</div>
</template>

<script>
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { NcActions, NcActionButton, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import CollectivesIcon from './Icon/CollectivesIcon.vue'
import EmoticonOutlineIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import EditButton from './Page/EditButton.vue'
import PageActionMenu from './Page/PageActionMenu.vue'
import PageTemplateIcon from './Icon/PageTemplateIcon.vue'
import TextEditor from './Page/TextEditor.vue'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import pageMixin from '../mixins/pageMixin.js'
import { showError } from '@nextcloud/dialogs'
import { RENAME_PAGE } from '../store/actions.js'

export default {
	name: 'Page',

	components: {
		CollectivesIcon,
		EditButton,
		EmoticonOutlineIcon,
		NcActionButton,
		NcActions,
		NcButton,
		NcEmojiPicker,
		NcLoadingIcon,
		PageActionMenu,
		PageTemplateIcon,
		TextEditor,
	},

	mixins: [
		isMobile,
		pageMixin,
	],

	data() {
		return {
			editMode: false,
			isEditorReady: false,
			newTitle: '',
			titleIsTruncated: false,
		}
	},

	computed: {
		...mapGetters([
			'currentPage',
			'currentCollective',
			'currentCollectiveCanEdit',
			'indexPage',
			'isPublic',
			'isTemplatePage',
			'landingPage',
			'loading',
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
				// Add parent page names in reverse order
				filePath.split('/').forEach(part => part && parts.unshift(part))
				if (!this.indexPage) {
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

		titleFormButtonIsLoading() {
			return this.loading('pageUpdate') || (this.editMode && !this.isEditorReady)
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
			this.initEditMode()
			this.initTitleEntry()
		},
	},

	mounted() {
		document.title = this.documentTitle
		this.initEditMode()
		this.initTitleEntry()
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
		}),

		initTitleEntry() {
			if (this.loading('newPage')) {
				this.newTitle = ''
				this.$nextTick(this.focusTitle)
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
			this.$refs.texteditor.focusEditor()
		},

		async setPageEmoji(emoji) {
			await this.setEmoji(this.currentPage.parentId, this.currentPage.id, emoji)
		},

		openPageEmojiPicker() {
			this.$refs['page-emoji-picker'].open = true
			this.hide('pageEmojiPicker')
		},

		initEditMode() {
			// Open in edit mode when pageMode is set, for template pages and for new pages
			this.editMode = !!this.currentCollective.pageMode || this.isTemplatePage || !!this.loading('newPage')
		},

		startEdit() {
			this.editMode = true
		},

		stopEdit() {
			this.editMode = false
		},

		readyEditor() {
			this.isEditorReady = true
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
</style>

<style lang="scss">
@media print {
	/* Don't print emoticon button (if page doesn't have an emoji set) */
	.edit-button, .action-item, .emoji-picker-emoticon {
		display: none !important;
	}
}
</style>
