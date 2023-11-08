import { generateUrl } from '@nextcloud/router'
import { mapGetters } from 'vuex'

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
	computed: {
		...mapGetters([
			'collectiveParam',
			'currentCollective',
			'currentPageFilePath',
			'isIndexPage',
			'isPublic',
			'pageById',
		]),
	},

	methods: {
		followLink(_event, attrs) {
			return this.handleCollectiveLink(attrs)
				|| this.handleSameOriginLink(attrs)
				|| this.handleRelativeFileLink(attrs)
				|| window.open(attrs.href, '_blank')
		},

		// E.g. `https://cloud.example.org/apps/collectives/mycollective/...` or `/apps/collectives/mycollective/...`
		handleCollectiveLink({ href }) {
			const collectiveParam = encodeURIComponent(this.collectiveParam)

			// If we're on index page, append `/` to location to make `URL()` append relative paths correctly
			let windowLocation = window.location.origin + window.location.pathname
			if (this.isIndexPage) {
				windowLocation = `${windowLocation}/`
			}

			// Add origin for local links and resolve relative paths
			const full = new URL(href, windowLocation)
			href = full.href

			const baseUrl = new URL(generateUrl('/apps/collectives'), window.location)
			if (!href.startsWith(baseUrl.href)) {
				// Ignore everything except links to local collectives app
				return false
			}

			// Try to resolve relative links to markdown files
			if (href.includes('.md?fileId=')) {
				// With `fileId` parameter
				href = href.replace('.md?', '?')
			} else if (href.endsWith('.md')) {
				// Without `fileId` parameter
				href = href.slice(0, -'.md'.length)
				if (href.endsWith('/Readme')) {
					href = href.slice(0, -'/Readme'.length)
				}
			}

			// Special treatment for links to current collective
			let collectivePath = href.replace(baseUrl.href, '')
			const publicPrefix = `/p/${this.currentCollective.shareToken}/`

			if (collectivePath === `/${collectiveParam}` || collectivePath.startsWith(`/${collectiveParam}/`)) {
				// If link contains a fileId, handle only existing pages
				// Required to not break relative links to attachments in the Collectives folder
				if (collectivePath.includes('?fileId=')) {
					const fileId = parseInt(collectivePath.match(/^[^?]*\?fileId=(\d+)/)[1])
					if (!this.pageById(fileId)) {
						return false
					}
				}

				// In public share, rewrite private link to own collective to a public share
				if (this.isPublic) {
					collectivePath = `${publicPrefix}${collectivePath}`
				}
			}

			// When not in public share, rewrite link to public share of own collective to internal
			if (!this.isPublic && collectivePath.startsWith(publicPrefix)) {
				collectivePath = collectivePath.replace(publicPrefix, '')
			}

			this.$router.push(collectivePath)
			return true
		},

		// E.g. `https://cloud.example.org/
		handleSameOriginLink({ href }) {
			if (href.match(new RegExp('^' + window.location.origin + '/'))) {
				window.open(href)
			}
		},

		handleRelativeFileLink({ href }) {
			if (!href.match(/^[a-zA-Z]*:/)) {
				const fileIdMatches = href.match(/^([^?]*)\?fileId=(\d+)/)
				if (!fileIdMatches || fileIdMatches.length < 2) {
					// href search params don't contain a fileId
					return false
				}
				const encodedRelPath = fileIdMatches[1]
				const relPath = decodeURI(encodedRelPath)
				const path = resolvePath(`/${this.currentPageFilePath}`, relPath)
				this.OCA.Viewer.open({ path })
				return true
			}
		},

		toggleOutlineFromTextComponent() {
			this.toggle('outline')
		},
	},
}
