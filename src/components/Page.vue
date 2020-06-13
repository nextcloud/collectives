<template>
	<AppContent>
		<div>
			<div id="action-menu">
				<Actions>
					<ActionButton v-if="!isVersion"
						icon="icon-edit"
						@click="edit = !edit">
						{{ t('wiki', 'Toggle edit mode') }}
					</ActionButton>
					<ActionButton v-else
						icon="icon-history"
						@click="revertVersion">
						{{ t('wiki', 'Restore this version') }}
					</ActionButton>
				</Actions>
				<Actions v-if="!isVersion">
					<ActionButton
						icon="icon-delete"
						@click="deletePage">
						{{ t('wiki', 'Delete page') }}
					</ActionButton>
				</Actions>
				<Actions>
					<ActionButton icon="icon-menu" @click="$emit('toggleSidebar')">
						{{ t('wiki', 'Toggle sidebar') }}
					</ActionButton>
				</Actions>
			</div>
			<h1 v-if="!isVersion" id="titleform" class="page-title">
				<input v-if="!isVersion"
					ref="title"
					v-model="newTitle"
					:placeholder="t('wiki', 'Title')"
					type="text"
					:disabled="updating || !savePossible"
					@keypress.13="focusEditor"
					@blur="renamePage">
			</h1>
			<h1 v-else class="page-title">
				{{ page.title }}
			</h1>
			<RichText v-if="readOnly"
				:page-id="page.id"
				:page-url="pageUrl"
				:as-placeholder="preview && edit"
				:is-version="isVersion" />
			<component :is="handler.component"
				v-show="!readOnly"
				ref="editor"
				:key="'editor-' + page.id + currentVersionTimestamp"
				:fileid="page.id"
				:basename="page.filename"
				:filename="'/' + page.basedir + '/' + page.filename"
				:has-preview="true"
				:active="true"
				mime="text/markdown"
				class="file-view active"
				@ready="hidePreview" />
		</div>
	</AppContent>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import RichText from './RichText'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
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
		version: {
			type: Object,
			required: false,
			default: null,
		},
		currentVersionTimestamp: {
			type: Number,
			required: true,
		},
		updating: {
			type: Boolean,
			required: false,
		},
	},

	data: function() {
		return {
			edit: false,
			preview: true,
		}
	},

	computed: {
		readOnly() {
			return this.preview || !this.edit || this.isVersion
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
			return this.isVersion
				? this.version.downloadUrl
				: generateRemoteUrl(`dav/files/${this.getUser}/${this.page.basedir}/${this.page.filename}`)
		},

		/**
		 * @returns {boolean}
		 */
		isVersion() {
			return !!this.version
		},

		/**
		 * @returns {string}
		 */
		getUser() {
			return getCurrentUser().uid
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
			this.edit = false
			if (this.page.newTitle === '') {
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

		/**
		 * Revert page to an old version
		 */
		async revertVersion() {
			try {
				const user = getCurrentUser().uid
				const restoreFolderUrl = generateRemoteUrl(`dav/versions/${user}/restore/${this.page.id}`)
				await axios({
					method: 'MOVE',
					url: this.version.downloadUrl,
					headers: {
						'Destination': restoreFolderUrl,
					},
				})
				this.$emit('resetVersion')
				showSuccess(t('wiki', 'Reverted {page} to revision {timestamp}.', {
					page: this.page.title,
					timestamp: this.version.relativeTimestamp,
				}))
			} catch (e) {
				showError(t('wiki', 'Failed to revert {page} to revision {timestamp}.', {
					page: this.page.title,
					timestamp: this.version.relativeTimestamp,
				}))
				console.error('Failed to move page to restore folder', e)
			}
		},
	},
}
</script>

<style scoped>
	#app-content > div {
		width: 100%;
		height: 100%;
		padding: 20px;
		display: flex;
		flex-direction: column;
		flex-grow: 1;
	}

	.page-title, #titleform input[type="text"] {
		font-size: 24px;
		width: 80%;
		max-width: 670px;
		border: none;
		text-align: center;
	}

	#action-menu {
		position: absolute;
		right: 0;
	}
</style>
