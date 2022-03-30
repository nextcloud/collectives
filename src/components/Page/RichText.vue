<template>
	<div id="text-wrapper" class="richEditor">
		<div id="text" class="editor">
			<div :class="{menubar: true, loading}">
				<div class="menubar-icons" />
			</div>
			<div v-if="!loading">
				<EditorContent v-if="editor"
					class="editor__content"
					:editor="editor" />
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
	OrderedList,
	Blockquote,
	CodeBlock,
	HorizontalRule,
	Italic,
	Strike,
	Bold,
} from 'tiptap-extensions'
import { BulletList, Image, ListItem } from '../../nodes'
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

		timestamp: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			loading: true,
			pageContent: null,
			editor: null,
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'shareTokenParam',
			'currentPage',
			'currentPageDavUrl',
		]),

		/**
		 * @return {string}
		 */
		davUrl() {
			return (this.pageUrl !== null ? this.pageUrl : this.currentPageDavUrl)
		},

		currentDirectory() {
			const { collectivePath, filePath } = this.currentPage
			return [collectivePath, filePath].filter(Boolean).join('/')
		},

		/**
		 * @return {object}
		 */
		markdownit() {
			return MarkdownIt('commonmark', { html: false, breaks: false })
				.enable('strikethrough')
				.use(taskLists, { enable: true, labelAfter: true })
		},

		/**
		 * @return {string}
		 */
		htmlContent() {
			return this.markdownit.render(this.pageContent)
		},

	},

	watch: {
		'davUrl'() {
			this.initPageContent()
		},
		'timestamp'() {
			this.updatePageContent()
		},
	},

	mounted() {
		this.$emit('loading')
		this.initPageContent()
	},

	methods: {
		/**
		 * Get markdown content of page
		 */
		async getPageContent() {
			// Authenticate via share token for public shares
			let axiosConfig = {}
			if (this.isPublic) {
				axiosConfig = {
					auth: {
						username: this.shareTokenParam,
					},
				}
			}

			try {
				const content = await axios.get(this.davUrl, axiosConfig)
				// content.data will attempt to parse as json
				// but we want the raw text.
				this.pageContent = content.request.responseText
				if (!this.pageContent) {
					this.$emit('empty')
				}
			} catch (e) {
				const { id } = this.currentPage
				console.error(`Failed to fetch content of page ${id}`, e)
			}
		},

		async initPageContent() {
			this.loading = true
			await this.getPageContent()
			this.loading = false
			this.editor = this.createEditor()
			this.$nextTick(() => { this.$emit('ready') })
		},

		/**
		 * Update markdown content of page
		 */
		async updatePageContent() {
			await this.getPageContent()
			this.editor.setContent(this.htmlContent)
		},

		/**
		 * @return {object}
		 */
		createEditor() {
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
					new Image({ currentDirectory: this.currentDirectory }),
				],
				content: this.htmlContent,
			})
		},

	},
}
</script>

<style scoped lang="scss">

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
	overflow: hidden;
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
@import './css/prosemirror';

@media print {
	.menubar {
		display: none !important;
	}

	#editor-wrapper, #text-wrapper {
		display: block !important;
		overflow: visible !important;
	}

	#titleform-allpages {
		page-break-after: avoid;
	}

	h1, h2, h3 {
		page-break-after: avoid;
	}
}
</style>
