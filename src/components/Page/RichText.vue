<template>
	<div id="text-wrapper" class="richEditor">
		<input id="sharingToken"
			type="hidden"
			name="sharingToken"
			:value="shareTokenParam">
		<div id="text" class="editor">
			<div :class="{menubar: true, loading}">
				<div class="menubar-icons" />
			</div>
			<div v-if="!loading">
				<ReadOnlyEditor class="editor__content"
					:content="content"
					:rich-text-options="richTextOptions"
					@click-link="followLink" />
			</div>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { mapGetters } from 'vuex'
import ReadOnlyEditor from '@nextcloud/text/package/components/ReadOnlyEditor'
import { generateUrl } from '@nextcloud/router'

const resolvePath = function(from, rel) {
	if (!rel) {
		return from
	}
	if (rel[0] === '/') {
		return rel
	}
	from = from.split('/')
	from.pop()
	rel = rel.split('/')
	while (rel[0] === '..' || rel[0] === '.') {
		if (rel[0] === '..') {
			from.pop()
		}
		rel.shift()
	}
	return from.concat(rel).join('/')
}

export default {
	name: 'RichText',

	components: {
		ReadOnlyEditor,
	},

	provide() {
		return {
			fileId: this.currentPage.id,
		}
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

		currentPage: {
			type: Object,
			required: true,
		},

	},

	data() {
		return {
			loading: true,
			content: null,
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'shareTokenParam',
			'currentPageDavUrl',
			'currentPageFilePath',
			'pageParam',
			'collectiveParam',
		]),

		/**
		 * @return {string}
		 */
		davUrl() {
			return (this.pageUrl !== null ? this.pageUrl : this.currentPageDavUrl)
		},

		richTextOptions() {
			return {
				currentDirectory: this.currentDirectory,
			}
		},

		currentDirectory() {
			const { collectivePath, filePath } = this.currentPage
			return [collectivePath, filePath].filter(Boolean).join('/')
		},

	},

	watch: {
		'davUrl'() {
			this.initPageContent()
		},
		'currentPage.timestamp'() {
			this.getPageContent()
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
				this.content = content.request.responseText
				if (!this.content) {
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
			this.$nextTick(() => { this.$emit('ready') })
		},

		followLink(_event, attrs) {
			return this.handleCollectiveLink(attrs)
				|| this.handleRelativeMarkdownLink(attrs)
				|| this.handleSameOriginLink(attrs)
				|| this.handleRelativeFileLink(attrs)
				|| window.open(attrs.href, '_blank')
		},

		handleCollectiveLink({ href }) {
			const baseUrl = new URL(generateUrl('/apps/collectives'), window.location)
			if (href.startsWith(baseUrl.href)) {
				this.$router.push(href.replace(baseUrl.href, ''))
				return true
			}
		},

		handleRelativeMarkdownLink({ href }) {
			const full = new URL(href, window.location)
			if (full.origin === window.location.origin
				&& href.includes('.md?fileId=')) {
				const pageParamOmitsReadme = this.currentPage.fileName === 'Readme.md'
					&& this.pageParam !== 'Readme.md'
				const prefix = pageParamOmitsReadme
					? (this.pageParam || this.collectiveParam) + '/'
					: ''
				this.$router.push(prefix + href.replace('.md?', '?'))
				return true
			}
		},

		handleSameOriginLink({ href }) {
			if (href.match('/^' + window.location.origin + '/')) {
				window.open(href)
			}
		},

		handleRelativeFileLink({ href }) {
			if (!href.match(/^[a-zA-Z]*:/)) {
				const encodedRelPath = href.match(/^([^?]*)\?fileId=(\d+)/)[1]
				const relPath = decodeURI(encodedRelPath)
				const path = resolvePath(`/${this.currentPageFilePath}`, relPath)
				this.OCA.Viewer.open({ path })
				return true
			}
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
	z-index: 100;
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
	position: absolute;
	display: flex;
	width: 100%;
	height: 100%;
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
	max-width: 800px;
	margin-left: auto;
	margin-right: auto;
}

#read-only-editor {
	overflow-x: hidden;
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
