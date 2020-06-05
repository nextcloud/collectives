<template>
	<div id="preview-container" :key="'preview-' + page.id">
		<div id="preview-wrapper" class="richEditor">
			<div id="preview" class="editor">
				<div :class="{menubar: true, loading}" />
				<div v-if="!loading">
					<EditorContent
						class="editor__content"
						:class="{ 'preview-revision': version }"
						:editor="editor" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateRemoteUrl } from '@nextcloud/router'

import MarkdownIt from 'markdown-it'
import taskLists from 'markdown-it-task-lists'

import { Editor, EditorContent } from 'tiptap'
import {
	HardBreak,
	Heading,
	Code,
	Link,
	BulletList,
	OrderedList,
	Blockquote,
	CodeBlock,
	HorizontalRule,
	Italic,
	Strike,
	ListItem,
} from 'tiptap-extensions'

export default {
	name: 'PagePreview',

	components: {
		EditorContent,
	},

	props: {
		pageLoading: {
			type: Boolean,
			required: false,
		},
		page: {
			type: Object,
			required: true,
		},
		version: {
			type: Boolean,
			required: true,
		},
	},

	data: function() {
		return {
			contentLoading: true,
			pageContent: null,
		}
	},

	computed: {
		loading() {
			return (this.pageLoading || this.contentLoading)
		},

		markdownit() {
			return MarkdownIt('commonmark', { html: false, breaks: false })
				.enable('strikethrough')
				.use(taskLists, { enable: true, labelAfter: true })
		},

		htmlContent() {
			return this.markdownit.render(this.pageContent)
		},

		editor() {
			return new Editor({
				editable: false,
				extensions: [
					new Heading(),
					new Code(),
					new Italic(),
					new Strike(),
					new HardBreak(),
					new HorizontalRule(),
					new BulletList(),
					new OrderedList(),
					new Blockquote(),
					new CodeBlock(),
					new ListItem(),
					new Link({
						openOnClick: true,
					}),
				],
				content: this.htmlContent,
			})
		},
	},

	watch: {
		'page.id': function() {
			this.getPageContent()
		},
	},

	mounted() {
		this.getPageContent()
	},

	methods: {
		/**
		 * Get markdown content of page
		 */
		async getPageContent() {
			try {
				this.contentLoading = true
				const user = getCurrentUser().uid
				const content = await axios.get(generateRemoteUrl(`dav/files/${user}/${this.page.basedir}/${this.page.filename}`))
				this.pageContent = content.data
				this.contentLoading = false
			} catch (e) {
				console.error(`Failed to fetch content of page ${this.page.title}`, e)
			}
		},
	},
}
</script>
<style scoped lang="scss">

	#preview-container {
		display: block;
		width: 100%;
		max-width: 100%;
		height: 100%;
		left: 0;
		top: 50px;
		margin: 0 auto;
		position: relative;
		background-color: var(--color-main-background);
	}

	.menubar {
		position: fixed;
		position: -webkit-sticky;
		position: sticky;
		top: 0;
		display: flex;
		z-index: 10010;
		background-color: var(--color-main-background-translucent);
		height: 44px;
		opacity: 0;
	}

	.menubar.loading {
		opacity: 100%;
	}

	#preview-wrapper {
		display: flex;
		width: 100%;
		height: 100%;
		overflow: hidden;
		position: absolute;
		&.icon-loading {
			#editor {
				opacity: 0.3;
			}
		}
	}

	#preview, .editor {
		background: var(--color-main-background);
		color: var(--color-main-text);
		background-clip: padding-box;
		border-radius: var(--border-radius);
		padding: 0;
		position: relative;
		overflow-y: auto;
		overflow-x: hidden;
		width: 100%;
	}

	.editor__content {
		max-width: 670px;
		margin: auto;
		position: relative;
	}

</style>

<style lang="scss">
	#preview-wrapper {
		@import './../../apps/text/css/prosemirror';
	}

	#preview-container {
		height: calc(100% - 50px);
		top: 50px;
	}
</style>
