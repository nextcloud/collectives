<template>
	<div>
		<h1 id="titleform" class="page-title">
			<form @submit.prevent="renamePage">
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
					type="text"
					:disabled="!savePossible"
					@keypress.enter="focusEditor">
			</form>
			<button v-if="edit"
				class="edit-button primary"
				@click="stopEdit">
				<span class="icon icon-checkmark-white" />
				{{ t('collectives', 'Done') }}
			</button>
			<button v-else
				class="edit-button primary"
				@click="startEdit">
				<span class="icon icon-rename-white" />
				{{ t('collectives', 'Edit') }}
			</button>
			<Actions>
				<ActionButton
					icon="icon-menu"
					:close-after-click="true"
					@click="toggle('sidebar')" />
			</Actions>
		</h1>
		<RichText v-if="readOnly"
			:as-placeholder="preview && edit"
			@empty="emptyPreview" />
		<Editor v-show="!readOnly"
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
			newTitle: '',
			titleHasFocus: false,
			editToggle: EditState.Unset,
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

		doc() {
			return this.wrapper.$data.document
		},

		collectiveTitle() {
			const { emoji, name } = this.currentCollective
			return emoji ? `${emoji} ${name}` : name
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

		/**
		 * Return true if a page is selected and its title is not empty
		 * @returns {boolean}
		 */
		savePossible() {
			return this.currentPage && this.currentPage.title !== ''
		},

		wrapper() {
			return this.$refs.editor.$children[0].$children[0]
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
		'edit'(current, previous) {
			if (current && !previous && !this.preview) {
				this.$nextTick(this.focusEditor)
			}
		},
	},

	mounted() {
		this.initDocumentTitle()
		this.initTitleEntry()
	},

	methods: {
		...mapMutations(['done', 'load', 'toggle']),

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
				this.$nextTick(this.focusTitle)
				this.done('newPage')
			} else {
				this.newTitle = this.currentPage.title
			}
		},

		/**
		 * Rename currentPage on the server
		 */
		async renamePage() {
			this.titleHasFocus = false
			if (!this.newTitle || this.newTitle === this.currentPage.title) {
				return
			}
			try {
				await this.$store.dispatch(RENAME_PAGE, this.newTitle)
				this.$router.push(this.updatedPagePath)
				this.$store.commit(CLEAR_UPDATED_PAGE)
				if (this.currentPage.size === 0) {
					this.$emit('edit')
				}
				this.$store.dispatch(GET_PAGES)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename the page'))
			}
		},

		focusTitle() {
			this.$refs.title.focus()
			this.titleHasFocus = true
		},

		focusEditor() {
			this.titleHasFocus = false
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
				this.focusEditor()
			}
		},

		emptyPreview() {
			if (!this.titleHasFocus && this.editToggle === EditState.Unset) {
				this.edit = true
			}
		},

		startEdit() {
			this.previousSaveTimestamp = this.doc.lastSavedVersionTime
			this.edit = true
		},

		async stopEdit() {
			const wasDirty = this.wrapper.$data.dirty

			if (wasDirty) {
				await this.wrapper.close()
			}
			if (this.doc.lastSavedVersionTime !== this.previousSaveTimestamp
				|| wasDirty) {
				this.$store.dispatch(TOUCH_PAGE)
				this.$store.dispatch(GET_VERSIONS, this.currentPage.id)
			}
			this.edit = false
		},
	},
}
</script>

<style lang="scss">

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
</style>
