<template>
	<div>
		<h1 id="titleform" class="page-title">
			<TitleForm
				@typing="titleHasFocus = true"
				@done="focusEditor" />
			<EditToggle :edit="edit" @start="startEdit" @stop="stopEdit" />
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
		<component :is="handler.component"
			v-show="!readOnly"
			:key="`editor-${currentPage.id}`"
			ref="editor"
			:fileid="currentPage.id"
			:basename="currentPage.fileName"
			:filename="`/${currentPageFilePath}`"
			:has-preview="true"
			:active="true"
			mime="text/markdown"
			class="file-view active"
			@ready="hidePreview" />
	</div>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import EditToggle from './Page/EditToggle'
import RichText from './Page/RichText'
import TitleForm from './Page/TitleForm'

import { mapGetters, mapMutations } from 'vuex'
import {
	TOUCH_PAGE,
	GET_VERSIONS,
} from '../store/actions'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'Page',

	components: {
		ActionButton,
		Actions,
		AppContent,
		EditToggle,
		RichText,
		TitleForm,
	},

	data() {
		return {
			previousSaveTimestamp: null,
			preview: true,
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
		]),

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
		'edit'(current, previous) {
			if (current && !previous && !this.preview) {
				this.$nextTick(this.focusEditor)
			}
		},
		'currentPage.id'() {
			this.editToggle = EditState.Unset
		},
	},

	mounted() {
		this.initDocumentTitle()
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
			const doc = this.$refs.editor.$children[0].$data.document
			this.previousSaveTimestamp = doc.lastSavedVersionTime
			this.edit = true
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
				this.$store.dispatch(GET_VERSIONS, this.currentPage.id)
			}
			this.edit = false
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

</style>
