<template>
	<div id="text-container" :key="'text-' + currentPage.id">
		<div id="text-wrapper" class="richEditor">
			<div id="text" class="editor">
				<div :class="{menubar: true, loading}">
					<div class="menubar-icons" />
				</div>
				<div v-if="!loading">
					<EditorContent
						class="editor__content"
						:editor="editor" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { mapGetters } from 'vuex'

import MarkdownIt from 'markdown-it'
import taskLists from 'markdown-it-task-lists'

import { Editor, EditorContent } from 'tiptap'
import {
	HardBreak,
	Heading,
	Code,
	BulletList,
	OrderedList,
	Blockquote,
	CodeBlock,
	HorizontalRule,
	Italic,
	ListItem,
	Strike,
	Bold,
} from 'tiptap-extensions'
import { Image } from '../../nodes'
import Link from '../../marks/link'

export default {
	name: 'RichText',

	components: {
		EditorContent,
	},

	props: {
		// RichText is rendered as a placeholder
		// with a spinning wheel where the toolbar would be.
		asPlaceholder: {
			type: Boolean,
			required: false,
		},

		pageUrl: {
			type: String,
			required: false,
			default: null,
		},
	},

	data() {
		return {
			contentLoading: true,
			pageContent: null,
		}
	},

	computed: {
		...mapGetters([
			'currentPage',
			'currentPageDavUrl',
		]),

		/**
		 * @returns {boolean}
		 */
		loading() {
			return (this.pageLoading || this.contentLoading)
		},

		davUrl() {
			return (this.pageUrl !== null ? this.pageUrl : this.currentPageDavUrl)

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
					new Bold(),
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
					new Image(),
				],
				content: this.htmlContent,
			})
		},
	},

	watch: {
		'davUrl'() {
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
				const content = await axios.get(this.davUrl)
				// content.data will attempt to parse as json
				// but we want the raw text.
				this.pageContent = content.request.responseText
				if (!this.pageContent) {
					this.$emit('empty')
				}
				this.contentLoading = false
			} catch (e) {
				const { id } = this.currentPage
				console.error(`Failed to fetch content of page ${id}`, e)
			}
		},
	},
}
</script>

<style scoped lang="scss">
#text-container {
	display: block;
	width: 100%;
	max-width: 100%;
	left: 0;
	margin: 0 auto;
	background-color: var(--color-main-background);
}

.menubar {
	position: fixed;
	position: -webkit-sticky;
	position: sticky;
	top: 0;
	display: flex;
	background-color: var(--color-main-background-translucent);
	height: 44px;
}

.menubar.loading {
	opacity: 100%;
}

.menubar .menubar-icons {
	flex-grow: 1;
	margin-left: calc((100% - 660px) / 2);
}

.menubar-icons button {
	opacity: .4;
	background-color: var(--color-background-dark);
}

@media (max-width: 660px) {
	.menubar .menubar-icons {
		margin-left: 0;
	}
}

#text-wrapper {
	display: flex;
	width: 100%;
	height: 100%;
	overflow: hidden;
	position: absolute;
}

#text-wrapper.icon-loading #editor {
	opacity: 0.3;
}

#text, .editor {
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

.text-revision {
	background-color: lightcoral;
}
</style>

<style lang="scss">
#text-wrapper {
	@import './css/prosemirror';
}

#text-container {
	height: calc(100% - 50px);
	top: 50px;
}

@media print {
	.menubar {
		display: none !important;
	}

	#editor-wrapper, #text-wrapper {
		display: block !important;
		overflow: visible !important;
	}
}
</style>
