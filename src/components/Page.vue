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
			<button v-if="currentCollectiveCanEdit"
				class="edit-button primary"
				:title="editMode ? t('collectives', 'Stop editing') : t('collectives', 'Start editing')"
				@click="editMode ? stopEdit() : startEdit()">
				<span class="icon icon-white"
					:class="`icon-${toggleIcon}`" />
				{{ editMode && !waitForEditor ? t('collectives', 'Done') : t('collectives', 'Edit') }}
			</button>
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
				:reload-content="waitForRichText"
				:current-page="currentPage"
				@empty="emptyRichText"
				@ready="readyRichText" />
		</div>
		<Editor v-show="showEditor"
			:key="`edit-${currentPage.id}-${reloadCounter}`"
			ref="editor"
			@ready="readyEditor" />
	</div>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Editor from './Page/Editor'
import RichText from './Page/RichText'
import PageActions from './Page/PageActions'
import { showError } from '@nextcloud/dialogs'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import {
	RENAME_PAGE,
	TOUCH_PAGE,
	GET_PAGES,
	GET_VERSIONS,
} from '../store/actions'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'Page',

	components: {
		ActionButton,
		Actions,
		Editor,
		RichText,
		PageActions,
	},

	data() {
		return {
			previousSaveTimestamp: null,
			readMode: true,
			richTextWasEmpty: false,
			newTitle: '',
			editToggle: EditState.Unset,
			reloadCounter: 0,
			changed: false,
			scrollTop: 0,
			waitForRichText: false,
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'currentPage',
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

		toggleIcon() {
			if (this.loading('pageUpdate') || this.waitForEditor) {
				return 'loading-small'
			} else {
				return this.editMode ? 'checkmark' : 'rename'
			}
		},

		showRichText() {
			return this.readOnly
		},

		showEditor() {
			return !this.readOnly || this.waitForRichText
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
		},
		'documentTitle'() {
			document.title = this.documentTitle
		},
	},

	mounted() {
		document.title = this.documentTitle
		this.initTitleEntry()
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
		doc() {
			return this.wrapper()?.$data.document
		},

		// this is a method so it does not get cached
		wrapper() {
			return this.$refs.editor.$children[0].$children[0]
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
			this.wrapper()?.$editor?.commands.focus()
		},

		/**
		 * Set readMode to false
		 */
		readyEditor() {
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

		emptyRichText() {
			this.richTextWasEmpty = true
			if (this.editToggle === EditState.Unset) {
				this.startEdit()
			}
		},

		readyRichText() {
			this.waitForRichText = false
			if (this.changed) {
				this.changed = false
				this.reloadCounter += 1
			}
			// Wait a few milliseconds to load images
			setTimeout(() => {
				document.getElementById('text')?.scrollTo(0, this.scrollTop)
			}, 90)
		},

		startEdit() {
			this.scrollTop = document.getElementById('text')?.scrollTop || 0
			if (this.doc()) {
				this.previousSaveTimestamp = this.doc().lastSavedVersionTime
			}
			this.editMode = true
			this.$nextTick(() => {
				if (this.scrollTop === 0) {
					// TODO: do we want to focus the editor after toggeling edit?
					this.focusEditor()
				}
				document.getElementById('editor')?.scrollTo(0, this.scrollTop)
			})
		},

		async stopEdit() {
			this.renamePage()
			const wasDirty = this.wrapper()._computedWatchers.hasUnpushedChanges.value
				|| this.wrapper()._computedWatchers.hasUnsavedChanges.value
			const changed = wasDirty
				|| this.doc().lastSavedVersionTime !== this.previousSaveTimestamp
			// if there is still no page content we remind the user
		    if (this.richTextWasEmpty && !changed) {
				this.focusEditor()
				return
			}
			this.scrollTop = document.getElementById('editor')?.scrollTop || 0
			if (wasDirty) {
				this.load('pageUpdate')
				await this.wrapper().close()
				this.done('pageUpdate')
			}
			if (changed) {
				this.changed = true
				this.richTextWasEmpty = false
				this.dispatchTouchPage()
				if (!this.isPublic && this.hasVersionsLoaded) {
					this.dispatchGetVersions(this.currentPage.id)
				}
				this.waitForRichText = true
			}
			this.editMode = false
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

.edit-button {
	min-width: max-content;
	height: 44px;

	.icon {
		opacity: 1;
		margin-right: 8px;
	}
}

@media print {
	.edit-button, .action-item {
		display: none !important;
	}
}
</style>
