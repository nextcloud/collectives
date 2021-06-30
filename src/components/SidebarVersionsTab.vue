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
		<template v-else-if="!loading && versions.length">
			<ul>
				<li :class="{active: !version}">
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
				<li v-for="v in versions"
					:key="v.downloadUrl"
					:class="{active: (version && v.timestamp === version.timestamp)}">
					<div class="icon-container">
						<img class="icon"
							:src="iconUrl"
							width="44"
							height="44">
					</div>
					<div class="version-container">
						<div>
							<a class="openVersion" @click="clickPreviewVersion(v)">
								<span class="versiondate has-tooltip live-relative-timestamp" :data-timestamp="v.millisecondsTimestamp" :title="v.formattedTimestamp">
									{{ v.relativeTimestamp }}
								</span>
							</a>
						</div>
						<div class="version-details">
							<span class="size has-tooltip" :title="v.altSize">{{ v.humanReadableSize }}</span>
						</div>
					</div>
				</li>
			</ul>
		</template>

		<!-- no versions found -->
		<EmptyContent v-else icon="icon-history">
			<h2>{{ t('collectives', 'No other versions available') }}</h2>
			<template #desc>
				{{ t( 'collectives', 'After editing you can find old versions of the page here.') }}
			</template>
		</EmptyContent>
	</div>
	<!-- </AppSidebarTab> -->
</template>

<script>
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import moment from '@nextcloud/moment'
import { formatFileSize } from '@nextcloud/files'
import { mapState, mapGetters } from 'vuex'
import { SELECT_VERSION } from '../store/mutations'
import { GET_VERSIONS } from '../store/actions'

export default {
	name: 'SidebarVersionsTab',

	 components: {
		EmptyContent,
	},

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
	},

	data() {
		return {
			error: '',
			loading: true,
		}
	},

	computed: {
		...mapState({
			versions: (state) => state.versions.versions,
		}),
		...mapGetters([
			'version',
		]),

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
	},

	beforeMount() {
		this.getPageVersions()
	},

	methods: {

		/**
		 * Get versions of a page
		 */
		async getPageVersions() {
			try {
				this.loading = true
				this.$store.dispatch(GET_VERSIONS, this.pageId)
				this.loading = false
			} catch (e) {
				this.error = t('collectives', 'Could not get page versions')
				this.loading = false
				console.error('Failed to get page versions', e)
			}
		},

		/**
		 * Select page version object to display
		 * @param {object} version Page version object
		 */
		clickPreviewVersion(version) {
			this.$store.commit(SELECT_VERSION, version)
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

li.active {
	background-color: var(--color-background-dark);
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
