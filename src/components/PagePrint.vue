<template>
	<div id="text-container" :key="'text-' + page.id" class="page">
		<h1 v-if="page.parentId === 0" id="page-title-collective" class="page-title page-title-collective">
			{{ currentCollectiveTitle }}
		</h1>
		<h1 v-else class="page-title page-title-subpage">
			{{ pageTitleString }}
		</h1>
		<RichTextReader v-if="pageContent"
			class="editor__content"
			:content="pageContent" />
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import { RichTextReader, ImageResolver, IMAGE_RESOLVER } from '@nextcloud/text'
import { getCurrentUser } from '@nextcloud/auth'
import pageContentMixin from '../mixins/pageContentMixin.js'

export default {
	name: 'PagePrint',

	components: {
		RichTextReader,
	},

	mixins: [
		pageContentMixin,
	],

	provide() {
		const val = {}
		Object.defineProperties(val, {
			[IMAGE_RESOLVER]: { get: () => this.imageResolver },
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
			pageContent: null,
		}
	},

	computed: {
		...mapGetters([
			'currentCollectiveTitle',
			'pageDavUrl',
			'pageDirectory',
			'isPublic',
			'shareTokenParam',
		]),

		imageResolver() {
			return new ImageResolver({
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
		this.getPageContent().then(() => {
			this.$emit('ready')
		})
	},

	methods: {
		async getPageContent() {
			this.pageContent = await this.fetchPageContent(this.pageDavUrl(this.page))
		},
	},
}
</script>

<style lang="scss" scoped>
@import '~@nextcloud/text/dist/style.css';

.page-title {
	font-size: 30px;
	line-height: 45px;
	padding: 8px 2px 2px 8px;
	margin: auto;
	max-width: 670px;

	overflow: hidden;
	text-overflow: ellipsis;

	&-collective {
		font-size: 35px;
	}

	&-subpage {
		page-break-before: always;
	}
}

#read-only-editor {
	overflow-x: hidden;
}

.editor__content {
	max-width: 670px;
	margin: auto;
	position: relative;
}

::v-deep #read-only-editor div.ProseMirror {
	margin-top: revert;
}
</style>
