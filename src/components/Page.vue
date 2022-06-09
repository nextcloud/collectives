<template>
	<div>
		<h1 id="titleform" class="page-title">
			<form @submit.prevent="renamePage(); startEdit()">
				<input v-if="landingPage"
					class="title"
					type="text"
					disabled
					:value="currentCollectiveTitle">
				<input v-else-if="isTemplatePage"
					class="title"
					type="text"
					disabled
					:value="t('collectives', 'Template')">
				<input v-else
					ref="title"
					v-model="newTitle"
					class="title"
					:placeholder="t('collectives', 'Title')"
					type="text"
					:disabled="!currentCollectiveCanEdit"
					@blur="renamePageOnBlur();">
			</form>
			<Button v-if="currentCollectiveCanEdit"
				v-tooltip="editMode ? t('collectives', 'Stop editing') : t('collectives', 'Start editing')"
				:aria-label="editMode ? t('collectives', 'Stop editing') : t('collectives', 'Start editing')"
				class="titleform-button"
				type="primary"
				@click="editMode ? stopEdit() : startEdit()">
				<template #icon>
					<LoadingIcon v-if="loading('pageUpdate') || waitForEditor" class="animation-rotate" :size="20" />
					<CheckIcon v-else-if="editMode" :size="20" />
					<PencilIcon v-else :size="20" />
				</template>
				{{ editMode && !waitForEditor ? t('collectives', 'Done') : t('collectives', 'Edit') }}
			</Button>
			<PageActions v-if="currentCollectiveCanEdit" />
			<Actions v-show="!showing('sidebar')">
				<ActionButton icon="icon-menu-sidebar"
					:close-after-click="true"
					@click="toggle('sidebar')" />
			</Actions>
		</h1>
		<div v-show="showRichText" id="text-container" :key="'text-' + currentPage.id">
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
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Button from '@nextcloud/vue/dist/Components/Button'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import CheckIcon from 'vue-material-design-icons/Check'
import LoadingIcon from 'vue-material-design-icons/Loading'
import PencilIcon from 'vue-material-design-icons/Pencil'
import Editor from './Page/Editor.vue'
import RichText from './Page/RichText.vue'
import PageActions from './Page/PageActions.vue'
import { showError } from '@nextcloud/dialogs'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import {
	RENAME_PAGE,
	TOUCH_PAGE,
	GET_PAGES,
	GET_VERSIONS,
} from '../store/actions.js'
import pageContentMixin from '../mixins/pageContentMixin.js'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'Page',

	components: {
		ActionButton,
		Actions,
		Button,
		CheckIcon,
		Editor,
		LoadingIcon,
		PencilIcon,
		RichText,
		PageActions,
	},

	directives: {
		Tooltip,
	},

	mixins: [
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
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'currentPage',
			'currentPageDavUrl',
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentCollectiveTitle',
			'hasVersionsLoaded',
			'indexPage',
			'landingPage',
			'pageParam',
			'loading',
			'showing',
			'isTemplatePage',
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
	},

	mounted() {
		document.title = this.documentTitle
		this.initTitleEntry()
		this.getPageContent()
	},

	methods: {
		...mapMutations(['done', 'load', 'toggle']),

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

	    renamePageOnBlur() {
			// Cypress tests in ci trigger blur events randomly.
			if (window.Cypress) {
				return
			}
			return this.renamePage()
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
	},
}
</script>

<style lang="scss">
#titleform form {
	flex: auto;
}

#text-container {
	display: block;
	width: 100%;
	max-width: 670px;
	left: 0;
	margin: 0 auto;
	background-color: var(--color-main-background);
	height: calc(100% - 50px);
	top: 50px;
}

#text-container .editor__content {
	border: 2px solid var(--color-main-background);
	border-radius: var(--border-radius);
}

.editor__content {
	border: 2px;
}

.page-title {
	padding: 8px 2px 2px 8px;
	position: relative;
	margin: auto;
	max-width: 670px;
	margin-bottom: -50px;
	display: flex;

	.title {
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

// Leave space for page list toggle on small screens
// Editor/View: 670px, page list/details toggle: 44px
@media only screen and (max-width: 670px + 44px) {
	.page-title {
		padding: 8px 2px 2px 40px;
	}
}

#action-menu button {
	z-index: 1;
}

.titleform-button {
	height: 44px;
}

.animation-rotate {
	animation: rotate var(--animation-duration, 0.8s) linear infinite;
}

@media print {
	.titleform-button, .action-item {
		display: none !important;
	}
}
</style>
