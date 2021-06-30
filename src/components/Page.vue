<template>
	<div>
		<h1 id="titleform" class="page-title">
			<form @submit.prevent="renamePage(); focusEditor()">
				<input v-if="landingPage"
					class="title"
					type="text"
					disabled
					:value="collectiveTitle">
				<input v-else
					ref="title"
					v-model="newTitle"
					class="title"
					:placeholder="t('collectives', 'Title')"
					type="text">
			</form>
			<button class="edit-button primary"
				@click="edit ? stopEdit() : startEdit()">
				<span class="icon icon-white"
					:class="`icon-${toggleIcon}`" />
				{{ t('collectives', edit ? 'Done' : 'Edit') }}
			</button>
			<Actions>
				<ActionButton
					icon="icon-menu"
					:close-after-click="true"
					@click="toggle('sidebar')" />
			</Actions>
		</h1>
		<RichText v-if="readOnly"
			:key="`show-${currentPage.id}-${currentPage.timestamp}`"
			:as-placeholder="preview && edit"
			@empty="emptyPreview" />
		<Editor v-show="!readOnly"
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
import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import {
	RENAME_PAGE,
	TOUCH_PAGE,
	GET_PAGES,
	GET_VERSIONS,
} from '../store/actions'
import { CLEAR_UPDATED_PAGE } from '../store/mutations'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'Page',

	components: {
		ActionButton,
		Actions,
		Editor,
		RichText,
	},

	data() {
		return {
			previousSaveTimestamp: null,
			preview: true,
			previewWasEmpty: false,
			newTitle: '',
			editToggle: EditState.Unset,
			reloadCounter: 0,
		}
	},

	computed: {
		...mapGetters([
			'currentPage',
			'currentPageFilePath',
			'currentCollective',
			'indexPage',
			'landingPage',
			'pageParam',
			'updatedPagePath',
			'loading',
		]),

		collectiveTitle() {
			const { emoji, name } = this.currentCollective
			return emoji ? `${emoji} ${name}` : name
		},

		titleChanged() {
			return this.newTitle && this.newTitle !== this.currentPage.title
		},

		toggleIcon() {
			if (this.loading('pageUpdate')) {
				return 'loading'
			} else {
				return this.edit ? 'checkmark' : 'rename'
			}
		},

		readOnly() {
			return this.preview || !this.edit
		},

		edit: {
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
			this.initDocumentTitle()
		},
		'currentPage.id'() {
			this.initTitleEntry()
			this.editToggle = EditState.Unset
		},
		'titleChanged'(current, previous) {
			if (current && !previous) {
				this.edit = true
			}
		},
	},

	mounted() {
		this.initDocumentTitle()
		this.initTitleEntry()
	},

	methods: {
		...mapMutations(['done', 'load', 'toggle']),

		// this is a method so it does not get cached
		doc() {
			return this.wrapper().$data.document
		},

		// this is a method so it does not get cached
		wrapper() {
			return this.$refs.editor.$children[0].$children[0]
		},

		initDocumentTitle() {
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
			document.title = parts.join(' - ')
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
			} else {
				this.newTitle = this.currentPage.title
			}
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

		startEdit() {
			if (this.doc()) {
				this.previousSaveTimestamp = this.doc().lastSavedVersionTime
			}
			this.edit = true
			this.$nextTick(this.focusEditor)
		},

		async stopEdit() {
			this.renamePage()
			const wasDirty = this.wrapper().$data.dirty
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
			if (changed) {
				this.reloadCounter += 1
				this.previewWasEmpty = false
				this.$store.dispatch(TOUCH_PAGE)
				this.$store.dispatch(GET_VERSIONS, this.currentPage.id)
			}
			this.edit = false
		},

		/**
		 * Rename currentPage on the server
		 */
		async renamePage() {
			if (!this.titleChanged) {
				return
			}
			try {
				await this.$store.dispatch(RENAME_PAGE, this.newTitle)
				// The resulting title may be different due to sanitizing
				this.newTitle = this.currentPage.title
				this.$router.push(this.updatedPagePath)
				this.$store.commit(CLEAR_UPDATED_PAGE)
				this.$store.dispatch(GET_PAGES)
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
