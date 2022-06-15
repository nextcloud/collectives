<template>
	<div id="text-wrapper" class="richEditor">
		<input id="sharingToken"
			type="hidden"
			name="sharingToken"
			:value="shareTokenParam">
		<div id="text" class="editor">
			<div class="menubar">
				<div class="menubar-icons" />
			</div>
			<RichTextReader v-if="!loading"
				class="editor__content"
				:content="pageContent"
				@click-link="followLink" />
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import { RichTextReader, ImageResolver, IMAGE_RESOLVER } from '@nextcloud/text'
import { getCurrentUser } from '@nextcloud/auth'
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
		RichTextReader,
	},

	provide() {
		const val = {}
		Object.defineProperties(val, {
			[IMAGE_RESOLVER]: { get: () => this.imageResolver },
		})
		return val
	},

	props: {
		// RichText is rendered as a placeholder
		// with the spinning wheel where the toolbar would be.
		asPlaceholder: {
			type: Boolean,
			required: false,
			default: false,
		},

		currentPage: {
			type: Object,
			required: true,
		},

		pageContent: {
			type: String,
			required: false,
			default: null,
		},
	},

	data() {
		return {
			loading: true,
		}
	},

	computed: {
		...mapGetters([
			'shareTokenParam',
			'currentPageDirectory',
			'currentPageFilePath',
			'pageParam',
			'collectiveParam',
		]),

		imageResolver() {
			return new ImageResolver({
				fileId: this.currentPage.id,
				currentDirectory: '/' + this.currentPageDirectory,
				user: getCurrentUser(),
				shareToken: this.shareTokenParam,
			})
		},

	},

	mounted() {
		this.$nextTick(() => {
			this.loading = false
			this.$emit('ready')
		})

		// scroll text div from outer text-wrapper div
		document.getElementById('text-wrapper').addEventListener('wheel', this.scrollTextFromOutside)
	},

	unmounted() {
		document.getElementById('text-wrapper')?.removeEventListener('wheel', this.scrollTextFromOutside)
	},

	methods: {
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

		scrollTextFromOutside(e) {
			document.getElementById('text').scrollBy(e.deltaX, e.deltaY)
		},
	},
}
</script>

<style scoped lang="scss">
@import '~@nextcloud/text/dist/style.css';

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
