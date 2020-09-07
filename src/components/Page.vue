<template>
	<div>
		<h1 id="titleform" class="page-title">
			<input v-if="landingPage"
				class="title"
				type="text"
				disabled
				:value="`${collective.emoji} ${collective.title}`">
			<input v-else
				ref="title"
				v-model="newTitle"
				class="title"
				:placeholder="t('collectives', 'Title')"
				type="text"
				:disabled="updating || !savePossible"
				@keypress.13="focusEditor"
				@blur="renamePage">
			<Actions class="top-bar__button" close-after-click="true">
				<ActionButton v-if="edit"
					icon="icon-checkmark"
					@click="stopEdit">
					{{ t('collectives', 'Done with editing') }}
				</ActionButton>
				<ActionButton v-else
					icon="icon-rename"
					@click="startEdit">
					{{ t('collectives', 'Edit page') }}
				</ActionButton>
			</Actions>
			<Actions>
				<ActionButton v-if="!landingPage"
					icon="icon-delete"
					@click="$emit('deletePage')">
					{{ t('collectives', 'Delete page') }}
				</ActionButton>
				<ActionButton
					icon="icon-menu"
					:close-after-click="true"
					@click="$emit('showVersions')">
					{{ t('collectives', 'Show old versions') }}
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
			:key="'editor-' + page.id + '-' + page.timestamp"
			:fileid="page.id"
			:basename="page.fileName"
			:filename="filePath"
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
			previousSaveTimestamp: null,
			preview: true,
		}
	},

	computed: {

		landingPage() {
			return !this.$store.getters.pageParam
		},

		page() {
			return this.$store.getters.currentPage
		},

		collective() {
			return this.$store.getters.currentCollective
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
				`dav/files/${this.davPath}`
			)
		},

		/**
		 * Path of the file via dav
		 * @returns {string}
		 */
		davPath() {
			const parts = this.page.filePath.split('/')
			parts.splice(2, 1)
			return parts
				.map(p => encodeURIComponent(p))
				.join('/')
		},

		/**
		 * Path of the file inside users home dir
		 * @returns {string}
		 */
		filePath() {
			const parts = this.page.filePath.split('/')
			parts.splice(1, 2)
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
			document.title = this.page.title + ' - Collective - Nextcloud'
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

		startEdit() {
			const doc = this.$refs.editor.$children[0].$data.document
			this.previousSaveTimestamp = doc.lastSavedVersionTime
			this.$emit('toggleEdit')
		},

		async stopEdit() {
			const wrapper = this.$refs.editor.$children[0]
			const doc = wrapper.$data.document
			if (wrapper.$data.dirty) {
				await wrapper.close()
				this.$store.dispatch('touchPage')
			} else if (doc.lastSavedVersionTime !== this.previousSaveTimestamp) {
				this.$store.dispatch('touchPage')
			}
			this.$emit('toggleEdit')
		},
	},
}
</script>

<style>
	#editor-container .editor__content {
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius);
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

	#action-menu button {
		z-index: 1;
	}
</style>
