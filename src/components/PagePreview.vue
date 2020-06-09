<template>
	<div id="preview-container" :key="'preview-' + pageId">
		<div id="preview-wrapper" class="richEditor">
			<div id="preview" class="editor">
				<div :class="{menubar: true, loading}" />
				<div v-if="!loading">
					<EditorContent
						class="editor__content"
						:class="{ 'preview-revision': isVersion }"
						:editor="editor" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'

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
		pageId: {
			type: Number,
			required: true,
		},
		pageUrl: {
			type: String,
			required: true,
		},
		isVersion: {
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
		/**
		 * @returns {boolean}
		 */
		loading() {
			return (this.pageLoading || this.contentLoading)
		},

		/**
		 * @returns {object}
		 */
		markdownit() {
			return MarkdownIt('commonmark', { html: false, breaks: false })
				.enable('strikethrough')
				.use(taskLists, { enable: true, labelAfter: true })
		},

		/**
		 * @returns {string}
		 */
		htmlContent() {
			return this.markdownit.render(this.pageContent)
		},

		/**
		 * @returns {object}
		 */
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
		'pageUrl': function() {
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
				const content = await axios.get(this.pageUrl)
				this.pageContent = content.data
				this.contentLoading = false
			} catch (e) {
				console.error(`Failed to fetch content of page ${this.pageId}`, e)
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

	.preview-revision {
		background-color: lightcoral;
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
