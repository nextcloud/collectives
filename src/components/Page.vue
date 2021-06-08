<template>
	<div>
		<h1 id="titleform" class="page-title">
			<TitleForm
				@typing="titleHasFocus = true"
				@done="focusEditor" />
			<EditToggle :edit="edit"
				:primary="!titleHasFocus"
				@start="startEdit"
				@stop="stopEdit" />
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
		Editor,
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
			'currentCollective',
			'indexPage',
			'landingPage',
			'pageParam',
		]),

		doc() {
			return this.wrapper.$data.document
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

		wrapper() {
			return this.$refs.editor.$children[0].$children[0]
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
			this.titleHasFocus = false
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

</style>
