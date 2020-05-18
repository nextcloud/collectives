<template>
	<div v-if="preview || !edit"
		:key="'preview-' + page.id"
		id="preview-container">
		<div id="preview-wrapper" class="richEditor">
			<div id="preview" class="editor">
				<div :class="{menubar: true, loading: (preview && edit)}" />
				<div>
					<EditorContent class="editor__content" :editor="editor" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import MarkdownIt from 'markdown-it'
import taskLists from 'markdown-it-task-lists'

import {Editor, EditorContent} from 'tiptap'
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
		preview: {
			type: Boolean,
			required: true,
		},
		edit: {
			type: Boolean,
			required: true,
		},
		page: {
			type: Object,
			required: true,
		},
	},
	computed: {
		markdownit() {
			return MarkdownIt('commonmark', { html: false, breaks: false })
				.enable('strikethrough')
				.use(taskLists, { enable: true, labelAfter: true })
		},

		htmlContent() {
			return this.markdownit.render(this.page.content)
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
