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
				:title="edit ? t('collectives', 'Stop editing') : t('collectives', 'Start editing')"
				@click="edit ? stopEdit() : startEdit()">
				<span class="icon icon-white"
					:class="`icon-${toggleIcon}`" />
				{{ edit && !waitForEdit ? t('collectives', 'Done') : t('collectives', 'Edit') }}
			</button>
			<PageActions v-if="currentCollectiveCanEdit" />
			<Actions v-show="!showing('sidebar')">
				<ActionButton icon="icon-menu-sidebar"
					:close-after-click="true"
					@click="toggle('sidebar')" />
			</Actions>
		</h1>
		<div v-if="readOnly" id="text-container" :key="'text-' + currentPage.id">
			<RichText :key="`show-${currentPage.id}`"
				:current-page="currentPage"
				@empty="emptyPreview"
				@ready="readyPreview" />
		</div>
		<Editor v-show="!readOnly || waitForPreview"
			:key="`edit-${currentPage.id}-${reloadCounter}`"
			ref="editor"
			@ready="hidePreview" />
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
			preview: true,
			previewWasEmpty: false,
			newTitle: '',
			editToggle: EditState.Unset,
			reloadCounter: 0,
			scrollTop: 0,
			waitForPreview: false,
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'currentPage',
			'currentPageFilePath',
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentCollectiveTitle',
			'hasVersionsLoaded',
			'indexPage',
			'landingPage',
			'pageParam',
			'loading',
			'visibleSubpages',
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
			if (this.loading('pageUpdate') || this.waitForEdit) {
				return 'loading-small'
			} else {
				return this.edit ? 'checkmark' : 'rename'
			}
		},

		waitForEdit() {
			return this.preview && this.edit
		},

		readOnly() {
			return !this.currentCollectiveCanEdit || this.preview || !this.edit
		},

		edit: {
			get() {
				return this.editToggle === EditState.Edit
			},
			set(val) {
				this.editToggle = val ? EditState.Edit : EditState.Read
			},
		},

		hasSubpages() {
			return this.visibleSubpages(this.currentPage.id).length
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
				this.done('newPage')
				return
			} else if (this.loading('newTemplate')) {
				// TODO: apparently focussing the editor doesn't work as expected
				this.$nextTick(this.focusEditor)
				this.done('newTemplate')
			}
			this.newTitle = this.currentPage.title
		},

		focusTitle() {
			this.$refs.title.focus()
		},

		focusEditor() {
			const editor = this.$el.querySelector('.ProseMirror')
			if (editor) {
				editor.focus()
			}
		},

		/**
		 * Set preview to false
		 */
		hidePreview() {
			this.preview = false
			if (this.edit) {
				if (this.doc()) {
					this.previousSaveTimestamp = this.doc().lastSavedVersionTime
				}
				this.focusEditor()
			}
		},

		emptyPreview() {
			this.previewWasEmpty = true
			if (this.editToggle === EditState.Unset) {
				this.startEdit()
			}
		},

		readyPreview() {
			this.waitForPreview = false
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
			this.edit = true
			this.$nextTick(() => {
				this.focusEditor()
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
		    if (this.previewWasEmpty && !changed) {
				this.focusEditor()
				return
			}
			if (wasDirty) {
				this.load('pageUpdate')
				await this.wrapper().close()
				this.done('pageUpdate')
			}
			this.scrollTop = document.getElementById('editor')?.scrollTop || 0
			if (changed) {
				this.reloadCounter += 1
				this.previewWasEmpty = false
				this.dispatchTouchPage()
				if (!this.isPublic && this.hasVersionsLoaded) {
					this.dispatchGetVersions(this.currentPage.id)
				}
			}
			this.waitForPreview = true
			this.edit = false
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
