<template>
	<!-- We're the only tab so far, so no need for AppSidebarTab
	<AppSidebarTab
		id="versions"
		name="Versions"
		icon="icon-history"
		:active-tab="activeTab"
		:class="{ 'icon-loading': loading }">
	-->
	<div id="versions">
		<!-- loading -->
		<div v-if="loading" class="emptycontent">
			<div class="icon icon-loading" />
		</div>

		<!-- error message -->
		<div v-else-if="error" class="emptycontent">
			<div class="icon icon-error" />
			<h2>{{ error }}</h2>
		</div>

		<!-- versions content -->
		<template v-else-if="!loading && versions">
			<ul :key="pageId + currentVersionTimestamp">
				<li :key="pageId + pageSize">
					<div class="icon-container">
						<img class="icon"
							:src="iconUrl"
							width="44"
							height="44">
					</div>
					<div class="version-container">
						<div>
							<a class="openVersion" @click="clickPreviewVersion(null)">
								<span class="versiondate has-tooltip" :title="pageFormattedTimestamp">
									{{ t('collectives', 'Current version') }}
								</span>
							</a>
						</div>
						<div class="version-details">
							<span class="size has-tooltip" :title="pageAltSize">{{ pageHumanReadableSize }}</span>
						</div>
					</div>
				</li>
				<li v-for="version in versions" :key="version.downloadUrl">
					<div class="icon-container">
						<img class="icon"
							:src="iconUrl"
							width="44"
							height="44">
					</div>
					<div class="version-container">
						<div>
							<a class="openVersion" @click="clickPreviewVersion(version)">
								<span class="versiondate has-tooltip live-relative-timestamp" :data-timestamp="version.millisecondsTimestamp" :title="version.formattedTimestamp">
									{{ version.relativeTimestamp }}
								</span>
							</a>
						</div>
						<div class="version-details">
							<span class="size has-tooltip" :title="version.altSize">{{ version.humanReadableSize }}</span>
						</div>
					</div>
				</li>
			</ul>
		</template>

		<!-- no versions found -->
		<div v-else class="emptycontent">
			<div class="icon icon-history" />
			<h2>{{ t('collectives', 'No other versions available') }}</h2>
		</div>
	</div>
	<!-- </AppSidebarTab> -->
</template>

<script>
// import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateRemoteUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { formatFileSize } from '@nextcloud/files'

export default {
	name: 'SidebarVersionsTab',

	/* components: {
		AppSidebarTab,
	}, */

	props: {
		pageId: {
			type: Number,
			required: true,
		},
		pageTitle: {
			type: String,
			required: true,
		},
		pageTimestamp: {
			type: Number,
			required: true,
		},
		pageSize: {
			type: Number,
			required: true,
		},
		currentVersionTimestamp: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			error: '',
			loading: true,
			versions: null,
		}
	},

	computed: {
		/**
		 * @returns {object}
		 */
		pageTime() {
			return moment.unix(this.pageTimestamp)
		},

		/**
		 * @returns {string}
		 */
		pageFormattedTimestamp() {
			return this.pageTime.format('LLL')
		},

		/**
		 * @returns {string}
		 */
		iconUrl() {
			return OC.MimeType.getIconUrl('text/markdown')
		},

		/**
		 * @returns {string}
		 */
		pageHumanReadableSize() {
			return formatFileSize(this.pageSize)
		},

		/**
		 * @returns {string}
		 */
		pageAltSize() {
			return n('files', '%n byte', '%n bytes', this.pageSize)
		},
	},

	watch: {
		'pageId'() {
			this.getPageVersions()
		},
		'currentVersionTimestamp'() {
			this.getPageVersions()
		},
	},

	beforeMount() {
		this.getPageVersions()
	},

	methods: {
		/**
		 * Convert an XML DOM object into a JSON object
		 * Copied from apps/workflowengine/src/components/Checks/MultiselectTag/api.js
		 * @param {object} xml XML object
		 * @returns {object}
		 */
		xmlToJson(xml) {
			let obj = {}

			if (xml.nodeType === 1) {
				if (xml.attributes.length > 0) {
					obj['@attributes'] = {}
					for (let j = 0; j < xml.attributes.length; j++) {
						const attribute = xml.attributes.item(j)
						obj['@attributes'][attribute.nodeName] = attribute.nodeValue
					}
				}
			} else if (xml.nodeType === 3) {
				obj = xml.nodeValue
			}

			if (xml.hasChildNodes()) {
				for (let i = 0; i < xml.childNodes.length; i++) {
					const item = xml.childNodes.item(i)
					const nodeName = item.nodeName
					if (typeof (obj[nodeName]) === 'undefined') {
						obj[nodeName] = this.xmlToJson(item)
					} else {
						if (typeof obj[nodeName].push === 'undefined') {
							const old = obj[nodeName]
							obj[nodeName] = []
							obj[nodeName].push(old)
						}
						obj[nodeName].push(this.xmlToJson(item))
					}
				}
			}
			return obj
		},

		/**
		 * Read string with XML content into DOMParser()
		 * Copied from apps/workflowengine/src/components/Checks/MultiselectTag/api.js
		 * @param {string} xml XML string
		 * @returns {object|null}
		 */
		parseXml(xml) {
			let dom = null
			try {
				dom = (new DOMParser()).parseFromString(xml, 'text/xml')
			} catch (e) {
				console.error('Failed to parse xml document', e)
			}
			return dom
		},

		/**
		 * Extract list of versions from an XML-encoded Nextcloud DAV API response
		 * @param {string} xml XML string
		 * @returns {array|null}
		 */
		xmlToVersionsList(xml) {
			const json = this.xmlToJson(this.parseXml(xml))
			const list = json['d:multistatus']['d:response']
			const result = []
			for (const index in list) {
				const version = list[index]['d:propstat']

				if (!version || version['d:status']['#text'] !== 'HTTP/1.1 200 OK') {
					continue
				}
				const url = list[index]['d:href']['#text']
				const time = moment.unix(url.split('/').pop())
				const size = version['d:prop']['d:getcontentlength']['#text']
				result.push({
					downloadUrl: generateRemoteUrl(url.split('remote.php/', 2)[1]),
					formattedTimestamp: time.format('LLL'),
					relativeTimestamp: time.fromNow(),
					timestamp: time.unix(),
					millisecondsTimestamp: time.valueOf(),
					humanReadableSize: formatFileSize(size),
					altSize: n('files', '%n byte', '%n bytes', size),
				})
			}
			return (result.length ? result : null)
		},

		/**
		 * Get versions of a page
		 */
		async getPageVersions() {
			try {
				this.loading = true
				const user = getCurrentUser().uid
				const versionsUrl = generateRemoteUrl(`dav/versions/${user}/versions/${this.pageId}`)
				const response = await axios({
					method: 'PROPFIND',
					url: versionsUrl,
					data: `<?xml version="1.0"?>
<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
 <d:prop>
  <d:getcontentlength />
  <d:getcontenttype />
  <d:getlastmodified />
 </d:prop>
</d:propfind>`,
				})
				this.versions = this.xmlToVersionsList(response.data)
				this.loading = false
			} catch (e) {
				this.error = t('collectives', 'Could not get page versions')
				this.loading = false
				console.error('Failed to get page versions', e)
			}
		},

		/**
		 * Emit page version object to the parent component
		 * @param {object} version Page version object
		 */
		clickPreviewVersion(version) {
			this.$emit('preview-version', version)
		},
	},
}
</script>

// Copied from apps/files_versions/src/css/versions.css
<style lang="scss" scoped>
	.clear-float {
		clear: both;
	}

	li {
		width: 100%;
		cursor: default;
		height: 56px;
		float: left;
		border-bottom: 1px solid rgba(100,100,100,.1);
	}

	li:last-child {
		border-bottom: none;
	}

	a, div > span {
		vertical-align: middle;
		opacity: .5;
	}

	li a {
		padding: 15px 10px 11px;
	}

	a:hover, a:focus {
		opacity: 1;
	}

	.icon-container {
		display: inline-block;
		vertical-align: top;
	}

	img {
		cursor: pointer;
		padding-right: 4px;
	}

	img.icon {
		cursor: default;
	}

	.version-container {
		display: inline-block;
	}

	.versiondate {
		min-width: 100px;
		vertical-align: super;
	}

	.version-details {
		text-align: left;
	}

	.version-details > span {
		padding: 0 10px;
	}
</style>
