<template>
	<router-link
		v-if="useRouter"
		:to="routerHref" />
	<a v-else-if="switchAppHref" :href="href" />
	<a v-else-if="leaveNcHref" :href="href" target="_blank" />
	<a v-else-if="hrefFileId" :href="viewerHref" @click.prevent="openViewer" />
	<a v-else :href="href" />
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { mapGetters } from 'vuex'

const absolutePath = function(base, rel) {
	if (!rel) {
		return base
	}
	if (rel[0] === '/') {
		return rel
	}
	base = base.split('/')
	rel = rel.split('/')
	while (rel[0] === '..' || rel[0] === '.') {
		if (rel[0] === '..') {
			base.pop()
		}
		rel.shift()
	}
	return base.concat(rel).join('/')
}

const basedir = function(file) {
	const end = file.lastIndexOf('/')
	return (end > 0)
		? file.slice(0, end)
		: file.slice(0, end + 1) // basedir('/toplevel') should return '/'
}

export default {
	name: 'LinkView',
	props: ['node', 'updateAttrs', 'view'], // eslint-disable-line
	computed: {
		...mapGetters(['collectiveParam', 'pageParam', 'currentPage']),
		schema() { return this.view.state.schema },
		href() {
			return this.node.attrs.href
		},
		useRouter() {
			return this.collectiveLink || this.relativeMarkdownLink
		},
		collectiveLink() {
	        return this.href.startsWith(this.routerBase)
		},
		relativeMarkdownLink() {
			const full = new URL(this.href, window.location)
			return full.origin === window.location.origin
				&& this.href.includes('.md?fileId=')
		},
		switchAppHref() {
			// not collectives link but on instance
			return !this.collectiveLink && this.href.match('/^' + window.location.origin + '/')
		},
		leaveNcHref() {
			// empty
			return !this.href
				// not on instance and starts with protocol
				|| (!this.switchAppHref && this.href.match(/^[a-zA-Z]*:/))
		},
		// returns an Array of the vars in the href:
		// [full string, relPath, fileId]
		hrefMatches() {
			return this.href.match(/^([^?]*)\?fileId=(\d+)/) || []
		},
		hrefFileId() {
			return this.hrefMatches[2]
		},
		relPath() {
			const relPath = this.hrefMatches[1]
			return relPath && unescape(relPath)
		},
		routerBase() {
			return new URL(
				generateUrl('/apps/collectives'),
				window.location
			).href
		},
		routerHref() {
			// handle absolute urls
	        if (this.href.startsWith(this.routerBase)) {
				return this.href.replace(this.routerBase, '')
			}
			// prefix relative route with the last part of the path
			// if it is ommitting the 'Readme.md'.
			const shortened = this.currentPage.fileName === 'Readme.md'
				&& this.pageParam !== 'Readme.md'
			const prefix = shortened
				? (this.pageParam || this.collectiveParam) + '/'
			    : ''
			return prefix + this.href.replace('.md?', '?')
		},
		viewerHref() {
			const dir = absolutePath('/Collective', basedir(this.relPath))
			return generateUrl(
				`/apps/files/?dir=${dir}&openfile=${this.hrefFileId}#relPath=${this.relPath}`
			)
		},
		pageId() {
			return Number(this.$route.query.fileId)
		},
	},
	methods: {
		openViewer() {
			const file = absolutePath('/Collective', this.relPath)
			this.OCA.Viewer.open({ path: file })
		},
	},
}
</script>
