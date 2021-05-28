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
					@keypress.13="focusEditor">
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
			:page-id="page.id"
			:page-url="currentPageDavUrl"
			:as-placeholder="preview && edit"
			@empty="emptyPreview" />
		<component :is="handler.component"
			v-show="!readOnly"
			:key="`editor-${page.id}`"
			ref="editor"
			:fileid="page.id"
			:basename="page.fileName"
			:filename="`/${currentPageFilePath}`"
			:has-preview="true"
			:active="true"
			mime="text/markdown"
			class="file-view active"
			@ready="hidePreview" />
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import RichText from './RichText'

import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import { RENAME_PAGE, TOUCH_PAGE, GET_VERSIONS } from '../store/actions'
import { CLEAR_UPDATED_PAGE } from '../store/mutations'

export default {
	name: 'Page',

	components: {
		ActionButton,
		Actions,
		AppContent,
		RichText,
	},

	props: {
		edit: {
			type: Boolean,
			required: false,
		},
	},

	data() {
		return {
			previousSaveTimestamp: null,
			preview: true,
			newTitle: '',
			titleHasFocus: false,
		}
	},

	computed: {
		...mapGetters([
			'currentPage',
			'currentPageFilePath',
			'currentPageDavUrl',
			'currentCollective',
			'indexPage',
			'landingPage',
			'pageParam',
			'updatedPagePath',
			'loading',
		]),

		page() {
			return this.currentPage
		},

		collective() {
			return this.currentCollective
		},

		collectiveTitle() {
			if (this.collective.emoji) {
				return `${this.collective.emoji} ${this.collective.name}`
			} else {
				return this.collective.name
			}
		},

		readOnly() {
			return this.preview || !this.edit
		},

		/**
		 * Fetch text app handler from Viewer app
		 * @returns {object}
		 */
		handler() {
			return OCA.Viewer.availableHandlers.find(h => h.id === 'text')
		},

		/**
		 * Return true if a page is selected and its title is not empty
		 * @returns {boolean}
		 */
		savePossible() {
			return this.page && this.page.title !== ''
		},

	},

	watch: {
		'pageParam'() {
			this.initDocumentTitle()
		},
		'currentPage.id'() {
			this.initTitleEntry()
		},
		'edit'(current, previous) {
			if (current && !previous && !this.preview) {
				this.$nextTick(this.focusEditor)
			}
		},
	},

	mounted() {
		this.init()
	},

	methods: {
		...mapMutations(['done', 'load', 'toggle']),

		initDocumentTitle() {
			const parts = [
				this.collective.name,
				t('collectives', 'Collectives'),
				'Nextcloud',
			]
			if (!this.landingPage) {
				if (this.indexPage) {
					parts.unshift(this.page.filePath ? this.page.filePath : this.page.title)
				} else {
					parts.unshift(this.page.filePath ? this.page.filePath + '/' + this.page.title : this.page.title)
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
				this.newTitle = this.page.title
			}
		},

		/**
		 * Rename currentPage on the server
		 */
		async renamePage() {
			this.titleHasFocus = false
			if (!this.newTitle || this.newTitle === this.page.title) {
				return
			}
			try {
				await this.$store.dispatch(RENAME_PAGE, this.newTitle)
				this.$router.push(this.updatedPagePath)
				this.$store.commit(CLEAR_UPDATED_PAGE)
				if (this.page.size === 0) {
					this.$emit('edit')
				}
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
			if (!this.titleHasFocus) {
				this.$emit('edit')
			}
		},

		startEdit() {
			const doc = this.$refs.editor.$children[0].$data.document
			this.previousSaveTimestamp = doc.lastSavedVersionTime
			this.$emit('toggleEdit')
		},

		async stopEdit() {
			const wrapper = this.$refs.editor.$children[0]
			const doc = wrapper.$data.document
			const wasDirty = wrapper.$data.dirty

			if (wasDirty) {
				await wrapper.close()
			}
			if (doc.lastSavedVersionTime !== this.previousSaveTimestamp
				|| wasDirty) {
				this.$store.dispatch(TOUCH_PAGE)
				this.$store.dispatch(GET_VERSIONS, this.page.id)
			}
			this.$emit('toggleEdit')
		},
	},
}
</script>

<style lang="scss">
	#editor-container .editor__content {
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius);
	}

	#editor-container .menububble {
		margin-bottom: 0px;
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
</style>
