<template>
	<div id="text-container" :key="'text-' + page.id" class="page sheet-view">
		<h1 v-if="page.parentId === 0" id="page-title-collective" class="page-title page-title-collective">
			{{ currentCollectiveTitle }}
		</h1>
		<h1 v-else class="page-title page-title-subpage">
			{{ pageTitleString }}
		</h1>
		<div ref="reader" class="sheet-view" data-collectives-el="reader" />
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import editorMixin from '../mixins/editorMixin.js'
import pageContentMixin from '../mixins/pageContentMixin.js'

export default {
	name: 'PagePrint',

	mixins: [
		editorMixin,
		pageContentMixin,
	],

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
			'isPublic',
			'shareTokenParam',
		]),

		pageTitleString() {
			return this.page.emoji ? `${this.page.emoji} ${this.page.title}` : this.page.title
		},
	},

	mounted() {
		this.$emit('loading')

		this.setupReader().then(() => {
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
