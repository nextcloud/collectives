<template>
	<div id="text-container" :key="'text-' + page.id" class="page">
		<h1 v-if="page.parentId === 0" id="page-title-collective" class="page-title page-title-collective">
			{{ currentCollectiveTitle }}
		</h1>
		<h1 v-else class="page-title page-title-subpage">
			{{ page.title }}
		</h1>
		<RichtextReader v-if="pageContent"
			class="editor__content"
			:content="pageContent" />
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import { RichtextReader } from '@nextcloud/text'
import pageContentMixin from '../mixins/pageContentMixin.js'

export default {
	name: 'PagePrint',

	components: {
		RichtextReader,
	},

	mixins: [
		pageContentMixin,
	],

	provide() {
		return {
			fileId: this.page.id,
			currentDirectory: this.pageDirectory(this.page),
		}
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

::v-deep #read-only-editor div.ProseMirror {
	margin-top: revert;
}
</style>
