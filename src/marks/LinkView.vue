<template>
	<router-link
		v-if="useRouter"
		:to="href.replace('.md?', '?')" />
	<a v-else-if="leaveHref" :href="href" />
	<a v-else-if="hrefFileId" :href="viewerHref" @click.prevent="openViewer" />
	<a v-else :href="href" />
</template>

<script>
import { generateUrl } from '@nextcloud/router'

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
		schema() { return this.view.state.schema },
		href() {
			return this.node.attrs.href
		},
		useRouter() {
			return this.collectiveLink
		},
		collectiveLink() {
			return this.href.includes('.md?fileId=')
				&& !this.href.includes('/') // for now we stay inside the Collective dir
		},
		leaveHref() {
			// empty
			return !this.href
				// starting with protocol:
				|| this.href.match(/^[a-zA-Z]*:/)
		},
		hrefFileId() {
			const [, id] = this.href.match(/^[^?]*\?fileId=(\d+)/)
			return id
		},
		relPath() {
			const [, relPath] = this.href.match(/^([^?]*)\?fileId=\d+/)
			return unescape(relPath)
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
			this.OCA.Viewer.open(file)
		},
	},
}
</script>
