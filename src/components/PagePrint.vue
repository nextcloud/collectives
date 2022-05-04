<template>
	<div id="text-container" :key="'text-' + page.id" class="page">
		<h1 v-if="page.parentId === 0" id="page-title-collective" class="page-title page-title-collective">
			{{ currentCollectiveTitle }}
		</h1>
		<h1 v-else class="page-title page-title-subpage">
			{{ page.title }}
		</h1>
		<ReadOnlyEditor v-if="content"
			class="editor__content"
			:content="content"
			:rich-text-options="richTextOptions" />
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import ReadOnlyEditor from '@nextcloud/text/package/components/ReadOnlyEditor'
import axios from '@nextcloud/axios'

export default {
	name: 'PagePrint',

	components: {
		ReadOnlyEditor,
	},

	provide() {
		return {
			fileId: this.page.id,
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
			content: null,
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

		richTextOptions() {
			return {
				currentDirectory: this.pageDirectory(this.page),
			}
		},
	},

	mounted() {
		this.$emit('loading')
		// TODO: only emit ready after images have been loaded
		this.getPageContent().then(() => this.$emit('ready'))
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
				const content = await axios.get(this.pageDavUrl(this.page), axiosConfig)
				// content.data will attempt to parse as json
				// but we want the raw text.
				this.content = content.request.responseText
			} catch (e) {
				const { id } = this.page
				console.error(`Failed to fetch content of page ${id}`, e)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
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
