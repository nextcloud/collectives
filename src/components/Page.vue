<template>
	<div>
		<div id="action-menu">
			<Actions>
				<ActionButton
					icon="icon-edit"
					@click="edit = !edit">
					{{ t('wiki', 'Toggle edit mode') }}
				</ActionButton>
			</Actions>
		</div>
		<h1 id="titleform" class="page-title">
			<input ref="title"
				v-model="newTitle"
				:placeholder="t('wiki', 'Title')"
				type="text"
				:disabled="updating || !savePossible"
				@keypress.13="focusEditor"
				@blur="renamePage">
		</h1>
		<RichText v-if="readOnly"
			:page-id="page.id"
			:page-url="pageUrl"
			:as-placeholder="preview && edit"
			@empty="emptyPreview" />
		<component :is="handler.component"
			v-show="!readOnly"
			ref="editor"
			:key="'editor-' + page.id"
			:fileid="page.id"
			:basename="page.filename"
			:filename="'/' + page.basedir + '/' + page.filename"
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

import { getCurrentUser } from '@nextcloud/auth'
import { generateRemoteUrl } from '@nextcloud/router'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'Page',

	components: {
		ActionButton,
		Actions,
		AppContent,
		RichText,
	},

	props: {
		page: {
			type: Object,
			required: true,
		},
		updating: {
			type: Boolean,
			required: false,
		},
	},

	data: function() {
		return {
			editToggle: EditState.Unset,
			preview: true,
		}
	},

	computed: {

		edit: {
			get: function() {
				return this.editToggle === EditState.Edit
			},
			set: function(val) {
				this.editToggle = val ? EditState.Edit : EditState.Read
			},
		},

		readOnly() {
			return this.preview || !this.edit
		},

		/**
		 * Fetch handlers for 'text/markdown' from Viewer app
		 * @returns {object}
		 */
		handler() {
			return OCA.Viewer.availableHandlers.filter(h => h.mimes.indexOf('text/markdown') !== -1)[0]
		},

		/**
		 * Return true if a page is selected and its title is not empty
		 * @returns {boolean}
		 */
		savePossible() {
			return this.page && this.page.title !== ''
		},

		/**
		 * Return the URL for currently selected page object
		 * @returns {string}
		 */
		pageUrl() {
			return generateRemoteUrl(
				`dav/files/${this.getUser}/${this.page.basedir}/${this.page.filename}`
			)
		},

		/**
		 * @returns {string}
		 */
		getUser() {
			return getCurrentUser().uid
		},

		emptyTitle() {
			return this.page.newTitle === ''
		},

		newTitle: {
			get: function() {
				return (typeof this.page.newTitle === 'string')
					? this.page.newTitle
					: this.page.title
			},
			set: function(val) {
				this.page.newTitle = val
			},
		},
	},

	watch: {
		page: function(val, oldVal) {
			this.init()
		},
	},

	mounted: function() {
		this.init()
	},

	methods: {
		init() {
			document.title = this.page.title + ' - Wiki - Nextcloud'
			this.preview = true
			this.editToggle = EditState.Unset
			if (this.emptyTitle) {
				this.$nextTick(this.focusTitle)
			}
		},

		renamePage() {
			if (this.page.newTitle) {
				this.$emit('renamePage', this.page.newTitle)
				this.edit = true
				this.$nextTick(this.focusEditor)
			}
		},

		focusTitle() {
			this.$refs.title.focus()
		},

		focusEditor() {
			this.$el.querySelector('.ProseMirror').focus()
		},

		deletePage() {
			this.$emit('deletePage', this.page.id)
		},

		/**
		 * Set preview to false
		 */
		hidePreview() {
			this.preview = false
		},

		emptyPreview() {
			if (this.editToggle === EditState.Unset && !this.emptyTitle) {
				this.edit = true
			}
		},
	},
}
</script>

<style>
	#editor-container .editor__content {
		box-shadow: 5px 5px 10px 0px #aaa;
	}

	.page-title {
		padding: 4px 2px 2px 8px;
		position: relative;
		margin: auto;
		max-width: 670px;
		margin-bottom: -50px;
	}

	#action-menu button {
		z-index: 1;
	}
</style>
