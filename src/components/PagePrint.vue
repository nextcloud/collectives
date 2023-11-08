<template>
	<div id="text-container" :key="'text-' + page.id" class="page sheet-view">
		<h1 v-if="page.parentId === 0" id="page-title-collective" class="page-title page-title-collective">
			{{ currentCollectiveTitle }}
		</h1>
		<h1 v-else class="page-title page-title-subpage">
			{{ pageTitleString }}
		</h1>
		<div v-if="useEditorApi"
			ref="reader"
			class="sheet-view"
			data-collectives-el="reader" />
		<RichTextReader v-else
			class="editor__content"
			:content="davContent" />
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import { RichTextReader, AttachmentResolver, ATTACHMENT_RESOLVER } from '@nextcloud/text'
import { getCurrentUser } from '@nextcloud/auth'
import editorMixin from '../mixins/editorMixin.js'
import pageContentMixin from '../mixins/pageContentMixin.js'

export default {
	name: 'PagePrint',

	components: {
		RichTextReader,
	},

	mixins: [
		editorMixin,
		pageContentMixin,
	],

	provide() {
		const val = {}
		Object.defineProperties(val, {
			[ATTACHMENT_RESOLVER]: { get: () => this.attachmentResolver },
		})
		return val
	},

	props: {
		page: {
			required: true,
			type: Object,
		},
	},

	data() {
		return {
			davContent: '',
		}
	},

	computed: {
		...mapGetters([
			'currentCollectiveTitle',
			'pageDavUrl',
			'pageDirectory',
			'isPublic',
			'shareTokenParam',
			'useEditorApi',
		]),

		attachmentResolver() {
			return new AttachmentResolver({
				fileId: this.page.id,
				currentDirectory: '/' + this.pageDirectory(this.page),
				user: getCurrentUser(),
				shareToken: this.shareTokenParam,
			})
		},

		pageTitleString() {
			return this.page.emoji ? `${this.page.emoji} ${this.page.title}` : this.page.title
		},
	},

	mounted() {
		this.$emit('loading')

		let readerPromise
		if (this.useEditorApi) {
			readerPromise = this.setupReader()
		} else {
			readerPromise = new Promise((resolve) => {
				resolve()
			})
		}

		readerPromise.then(() => {
			this.getPageContent().then(() => {
				this.$emit('ready')
			})
		})
	},

	methods: {
		async getPageContent() {
			this.davContent = await this.fetchPageContent(this.pageDavUrl(this.page))
			this.reader?.setContent(this.davContent)
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../css/editor';
@import '~@nextcloud/text/dist/style.css';

.page-title {
	font-size: 30px;
	line-height: 45px;
	padding: 8px 2px 2px 8px;

	overflow: hidden;
	text-overflow: ellipsis;

	&-collective {
		font-size: 35px;
	}

	&-subpage {
		page-break-before: always;
		break-before: always;
	}
}

:deep(.text-menubar) {
	display: none;
}

/* TODO: remove once removing LegacyEditor.vue+Reader.vue */
#read-only-editor {
	overflow-x: hidden;
}

/* TODO: remove once removing LegacyEditor.vue+Reader.vue */
:deep(.content-wrapper) {
	display: block !important;
}

/* TODO: remove once removing LegacyEditor.vue+Reader.vue */
:deep(.editor__content div.ProseMirror) {
	margin-top: 0;
	margin-bottom: 0;
	padding-top: 0;
	padding-bottom: 0;
}

:deep([data-collectives-el='reader'] .content-wrapper) {
	display: block !important;

	div.ProseMirror {
		margin-top: 0;
		margin-bottom: 0;
		padding-top: 0;
		padding-bottom: 0;
	}
}
</style>
