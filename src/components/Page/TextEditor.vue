<template>
	<div>
		<div v-show="showRichText"
			id="text-container"
			:key="'text-' + currentPage.id"
			:aria-label="t('collectives', 'Page content')">
			<RichText :key="`reader-${currentPage.id}`"
				:current-page="currentPage"
				:page-content="pageContent" />
		</div>
		<Editor v-if="currentCollectiveCanEdit"
			v-show="showEditor"
			:key="`editor-${currentPage.id}`"
			ref="editor"
			@ready="readyEditor" />
	</div>
</template>

<script>
import Editor from './Editor.vue'
import RichText from './RichText.vue'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import {
	GET_PAGES,
	GET_VERSIONS,
	TOUCH_PAGE,
} from '../../store/actions.js'
import pageContentMixin from '../../mixins/pageContentMixin.js'

export default {
	name: 'TextEditor',

	components: {
		Editor,
		RichText,
	},

	mixins: [
		pageContentMixin,
	],

	props: {
		editMode: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			pageContent: '',
			previousSaveTimestamp: null,
			readMode: true,
			scrollTop: 0,
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentPage',
			'currentPageDavUrl',
			'hasVersionsLoaded',
			'isPublic',
			'loading',
		]),

		showRichText() {
			return this.readOnly
		},

		showEditor() {
			return !this.readOnly
		},

		waitForEditor() {
			return this.readMode && this.editMode
		},

		readOnly() {
			return !this.currentCollectiveCanEdit || this.readMode || !this.editMode
		},
	},

	watch: {
		'editMode'(val) {
			if (val === true) {
				this.startEdit()
			} else {
				this.stopEdit()
			}
		},

		'currentPage.timestamp'() {
			if (this.currentPage.timestamp > this.previousSaveTimestamp) {
				this.getPageContent()
			}
		},
	},

	mounted() {
		this.getPageContent()
	},

	methods: {
		...mapMutations([
			'done',
		]),

		...mapActions({
			dispatchTouchPage: TOUCH_PAGE,
			dispatchGetPages: GET_PAGES,
			dispatchGetVersions: GET_VERSIONS,
		}),

		// this is a method so it does not get cached
		wrapper() {
			return this.$refs.editor?.$children[0].$children[0]
		},

		// this is a method so it does not get cached
		syncService() {
			// `$syncService` in Nexcloud 24+, `syncService` beforehands
			return this.wrapper()?.$syncService ?? this.wrapper()?.syncService
		},

		// this is a method so it does not get cached
		doc() {
			return this.wrapper()?.$data.document
		},

		focusEditor() {
			this.wrapper()?.$editor?.commands?.focus?.()
		},

		/**
		 * Set readMode to false
		 */
		readyEditor() {
			// Set pageContent if it's been empty before
			if (!this.pageContent) {
				this.pageContent = this.syncService()._getContent() || ''
			}
			this.readMode = false

			// Don't steal the focus from title if a new page
			if (this.loading('newPage')) {
				this.done('newPage')
				return
			}

			if (this.editMode) {
				if (this.doc()) {
					this.previousSaveTimestamp = this.doc().lastSavedVersionTime
				}
				this.$nextTick(this.focusEditor())
			}
			this.$emit('ready')
		},

		startEdit() {
			this.scrollTop = document.getElementById('text')?.scrollTop || 0
			if (this.doc()) {
				this.previousSaveTimestamp = this.doc().lastSavedVersionTime
			}
			this.$nextTick(() => {
				document.getElementById('editor')?.scrollTo(0, this.scrollTop)
			})
		},

		async stopEdit() {
			this.scrollTop = document.getElementById('editor')?.scrollTop || 0

			const pageContent = this.syncService()._getContent() || ''
			const changed = this.pageContent !== pageContent

			// switch back to edit if there's no content
			if (!pageContent) {
				this.$emit('start-edit')
				this.focusEditor()
				return
			}

			if (changed) {
				this.dispatchTouchPage()
				if (!this.isPublic && this.hasVersionsLoaded) {
					this.dispatchGetVersions(this.currentPage.id)
				}

				// Save pending changes in editor
				// TODO: detect missing connection and display warning
				this.syncService().save()

				this.pageContent = pageContent
			}

			this.$nextTick(() => {
				document.getElementById('text')?.scrollTo(0, this.scrollTop)
			})
		},

		async getPageContent() {
			this.pageContent = await this.fetchPageContent(this.currentPageDavUrl)
			if (!this.pageContent) {
				this.$emit('start-edit')
			}
		},

	},
}
</script>

<style lang="scss" scoped>

#text-container {
	display: block;
	width: 100%;
	max-width: 100%;
	left: 0;
	margin: 0 auto;
	background-color: var(--color-main-background);
}

:deep([data-text-el='editor-container']) {
	/* Remove scrolling mechanism from editor-container, required for menubar stickyness */
	overflow: visible;

	div.editor {
		/* Adjust to page titlebar height */
		div.text-menubar {
			margin: auto;
			top: 59px;
		}
	}
}
</style>
