<template>
	<AppContent>
		<div>
			<div id="action-menu">
				<Actions>
					<ActionButton icon="icon-edit" @click="edit = !edit">
						{{ t('wiki', 'Toggle edit mode') }}
					</ActionButton>
				</Actions>
				<Actions>
					<ActionButton icon="icon-menu" @click="$emit('toggleSidebar')">
						{{ t('wiki', 'Toggle sidebar') }}
					</ActionButton>
				</Actions>
			</div>
			<div id="titleform">
				{{ t('wiki', 'Title') }}:
				<input ref="title"
					v-model="page.newTitle"
					type="text"
					:disabled="updating || !savePossible"
					@blur="renamePage">
			</div>
			<PagePreview v-if="preview || !edit"
				:page="page"
				:page-loading="preview && edit"
				:version="true" />
			<component :is="handler.component"
				v-show="edit && !preview"
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
	</AppContent>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import PagePreview from './PagePreview'

export default {
	name: 'Page',

	components: {
		ActionButton,
		Actions,
		AppContent,
		PagePreview,
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
			edit: false,
			preview: true,
		}
	},

	computed: {
		/**
		 * Fetch handlers for 'text/markdown' from Viewer app
		 * @returns {object}
		 */
		handler() {
			return OCA.Viewer.availableHandlers.filter(h => h.mimes.indexOf('text/markdown') !== -1)[0]
		},

		/**
		 * Return true if a page is selected and its title is not empty
		 * @returns {Boolean}
		 */
		savePossible() {
			return this.page && this.page.title !== ''
		},
	},

	watch: {
		'page': function(val, oldVal) {
			if (!this.page.newTitle) {
				this.page.newTitle = this.page.title
			}
			document.title = this.page.title + ' - Wiki - Nextcloud'
			this.preview = true
		},
	},

	methods: {

		renamePage() {
			this.$emit('renamePage', this.page.newTitle)
		},

		/**
		 * Set preview to false
		 */
		hidePreview() {
			this.preview = false
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

	#titleform > input[type="text"] {
		width: 80%;
		max-width: 670px;
	}

	#action-menu {
		position: absolute;
		right: 0;
	}
</style>
