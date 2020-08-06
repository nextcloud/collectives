<template>
	<div>
		<h1 id="titleform" class="page-title">
			<input ref="title"
				v-model="newTitle"
				:placeholder="t('wiki', 'Title')"
				type="text"
				:disabled="updating || !savePossible"
				@keypress.13="focusEditor"
				@blur="renamePage">
			<Actions class="top-bar__button" close-after-click="true">
				<ActionButton v-if="edit"
					icon="icon-edit"
					@click="$emit('toggleEdit')">
					{{ t('wiki', 'View page without editing') }}
				</ActionButton>
				<ActionButton v-else
					icon="icon-rename"
					@click="$emit('toggleEdit')">
					{{ t('wiki', 'Edit page') }}
				</ActionButton>
			</Actions>
			<Actions>
				<ActionButton
					icon="icon-delete"
					@click="$emit('deletePage')">
					{{ t('wiki', 'Delete page') }}
				</ActionButton>
			</Actions>
		</h1>
		<RichText v-if="readOnly"
			:page-id="page.id"
			:page-url="pageUrl"
			:as-placeholder="preview && edit"
			@edit="$emit('edit')"
			@empty="emptyPreview" />
		<component :is="handler.component"
			v-show="!readOnly"
			ref="editor"
			:key="'editor-' + page.id"
			:fileid="page.id"
			:basename="page.fileName"
			:filename="'/' + page.filePath + '/' + page.fileName"
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
		edit: {
			type: Boolean,
			required: false,
		},
	},

	data: function() {
		return {
			preview: true,
		}
	},

	computed: {

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
				`dav/files/${this.filePath}`
			)
		},

		/**
		 * Path of the file via dav
		 * @returns {string}
		 */
		filePath() {
			const parts = this.page.filePath.split('/')
			parts.splice(2, 1)
			return parts.join('/')
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
			if (this.emptyTitle) {
				this.$nextTick(this.focusTitle)
			}
		},

		renamePage() {
			if (this.page.newTitle) {
				this.$emit('renamePage', this.page.newTitle)
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
			if (!this.emptyTitle) {
				this.$emit('edit')
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
		padding: 8px 2px 2px 8px;
		position: relative;
		margin: auto;
		max-width: 670px;
		margin-bottom: -50px;
		display: flex;
	}

	#action-menu button {
		z-index: 1;
	}
</style>
