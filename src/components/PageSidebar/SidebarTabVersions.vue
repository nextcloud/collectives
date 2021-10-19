<template>
	<AppContentList>
		<!-- loading -->
		<div v-if="loading('versions')" class="emptycontent">
			<div class="icon icon-loading" />
		</div>

		<!-- error message -->
		<div v-else-if="error" class="emptycontent">
			<div class="icon icon-error" />
			<h2>{{ error }}</h2>
		</div>

		<!-- versions list -->
		<template v-else-if="!loading('versions') && versions.length">
			<div class="app-content-list-item"
				:class="{active: !version}">
				<div class="app-content-list-item-icon">
					<div class="icon-page-white" />
				</div>
				<a class="app-content-list-item-link" @click="clickPreviewVersion(null)">
					<div class="app-content-list-item-line-one" :title="pageFormattedTimestamp">
						{{ t('collectives', 'Current version') }}
					</div>
					<div class="app-content-list-item-line-two" :title="pageAltSize">
						{{ pageHumanReadableSize }}
					</div>
				</a>
			</div>
			<div v-for="v in versions"
				:key="v.downloadUrl"
				class="app-content-list-item"
				:class="{active: (version && v.timestamp === version.timestamp)}">
				<div class="app-content-list-item-icon">
					<div class="icon-page-white" />
				</div>
				<a class="app-content-list-item-link" @click="clickPreviewVersion(v)">
					<div class="app-content-list-item-line-one live-relative-timestamp" :data-timestamp="v.millisecondsTimestamp" :title="v.formattedTimestamp">
						{{ v.relativeTimestamp }}
					</div>
					<div class="app-content-list-item-line-two" :title="v.altSize">
						{{ v.humanReadableSize }}
					</div>
				</a>
			</div>
		</template>

		<!-- no versions found -->
		<EmptyContent v-else icon="icon-history">
			<h2>{{ t('collectives', 'No other versions available') }}</h2>
			<template #desc>
				{{ t( 'collectives', 'After editing you can find old versions of the page here.') }}
			</template>
		</EmptyContent>
	</AppContentList>
</template>

<script>
import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import moment from '@nextcloud/moment'
import { formatFileSize } from '@nextcloud/files'
import { mapState, mapGetters, mapMutations } from 'vuex'
import { SELECT_VERSION } from '../../store/mutations'
import { GET_VERSIONS } from '../../store/actions'

export default {
	name: 'SidebarTabVersions',

	 components: {
		AppContentList,
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
		}
	},

	computed: {
		...mapState({
			versions: (state) => state.versions.versions,
		}),
		...mapGetters([
			'loading',
			'version',
		]),

		/**
		 * @return {string}
		 */
		pageFormattedTimestamp() {
			return moment.unix(this.pageTimestamp).format('LLL')
		},

		/**
		 * @return {string}
		 */
		pageHumanReadableSize() {
			return formatFileSize(this.pageSize)
		},

		/**
		 * @return {string}
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
		...mapMutations(['load', 'done']),

		/**
		 * Get versions of a page
		 */
		async getPageVersions() {
			this.load('versions')
			try {
				this.$store.dispatch(GET_VERSIONS, this.pageId)
			} catch (e) {
				this.error = t('collectives', 'Could not get page versions')
				console.error('Failed to get page versions', e)
			}
			this.done('versions')
		},

		/**
		 * Select page version object to display
		 *
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
.app-content-list {
	max-width: none;
	border-right: none;
}

.app-content-list-item {
	border-bottom: 1px solid rgba(100, 100, 100, 0.1);
}

.app-content-list-item .app-content-list-item-icon {
	line-height: 40px;
	width: 26px;
	height: 34px;
	left: 12px;
	font-size: 24px;
	background-color: var(--color-background-darker);
	border-radius: 4px;
}

.app-content-list-item .app-content-list-item-icon div {
	border-radius: 3px 12px 3px 3px;
}

.app-content-list .app-content-list-item .app-content-list-item-link {
	overflow: hidden;
	text-overflow: ellipsis;
}

.app-content-list .app-content-list-item .app-content-list-item-line-one {
	font-size: 120%;

	// Crop the string at the beginning, not end
	// TODO: Untested with RTL script
	text-align: left;
	direction: rtl;
}

.app-content-list .app-content-list-item .app-content-list-item-line-two {
	opacity: .5;
}

.app-content-list-item:hover {
	background-color: var(--color-background-hover);
}

.app-content-list-item .active {
	background-color: var(--color-background-dark);
}
</style>
