<template>
	<router-link v-if="useRouter" :to="to" :href="href" />
	<a v-else-if="leaveHref" :href="href" />
	<a v-else-if="withFileId" :href="viewerHref" />
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
		// TODO: use good heuristic
		useRouter() {
			return !this.href.includes('/') && this.href.includes('.md?fileId=')
		},
		leaveHref() {
			// empty
			return !this.href
				// starting with protocol:
				|| this.href.match(/^[a-zA-Z]*:/)
		},
		withFileId() { return this.href.match(/^([^?]*)\?fileId=(\d+)/) },
		to() { return this.href },
		viewerHref() {
			const [, relPath, id] = this.href.match(/^([^?]*)\?fileId=(\d+)/)
			const dir = absolutePath('/Wiki', basedir(relPath))
			return generateUrl(`/apps/files/?dir=${dir}&openfile=${id}#relPath=${relPath}`)
		},

	},
}
</script>
