<template>
	<NcAppContentList>
		<!-- loading -->
		<NcEmptyContent v-if="loading('versions')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- error message -->
		<NcEmptyContent v-else-if="error" :title="error">
			<template #icon>
				<AlertOctagonIcon />
			</template>
		</NcEmptyContent>

		<!-- versions list -->
		<template v-else-if="!loading('versions') && versions.length">
			<a @click="clickPreviewVersion(null)">
				<div class="app-content-list-item"
					:class="{active: !version}">
					<div class="app-content-list-item-icon item-icon-page">
						<PageIcon :size="26" fill-color="var(--color-main-background)" />
					</div>
					<div class="app-content-list-item-line-one" :title="pageFormattedTimestamp">
						{{ t('collectives', 'Current version') }}
					</div>
					<div class="app-content-list-item-line-two" :title="pageAltSize">
						{{ pageHumanReadableSize }}
					</div>
				</div>
			</a>
			<a v-for="v in versions"
				:key="v.downloadUrl"
				@click="clickPreviewVersion(v)">
				<div class="app-content-list-item"
					:class="{active: (version && v.timestamp === version.timestamp)}">
					<div v-if="loading(`version-${pageId}-${v.timestamp}`)"
						class="app-content-list-item-icon item-icon-loading">
						<NcLoadingIcon :size="26" fill-color="var(--color-main-background)" />
					</div>
					<div v-else class="app-content-list-item-icon item-icon-page">
						<PageIcon :size="26" fill-color="var(--color-main-background)" />
					</div>
					<div class="app-content-list-item-line-one live-relative-timestamp" :data-timestamp="v.millisecondsTimestamp" :title="v.formattedTimestamp">
						{{ v.relativeTimestamp }}
					</div>
					<div class="app-content-list-item-line-two" :title="v.altSize">
						{{ v.humanReadableSize }}
					</div>
				</div>
			</a>
		</template>

		<!-- no versions found -->
		<NcEmptyContent v-else
			:title="t('collectives', 'No other versions available')"
			:description="t( 'collectives', 'After editing you can find old versions of the page here.')">
			<template #icon>
				<RestoreIcon />
			</template>
		</NcEmptyContent>
	</NcAppContentList>
</template>

<script>
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { formatFileSize } from '@nextcloud/files'
import { NcAppContentList, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import AlertOctagonIcon from 'vue-material-design-icons/AlertOctagon.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import PageIcon from '../Icon/PageIcon.vue'
import { SELECT_VERSION } from '../../store/mutations.js'
import { GET_VERSIONS } from '../../store/actions.js'

export default {
	name: 'SidebarTabVersions',

	components: {
		AlertOctagonIcon,
		NcAppContentList,
		NcEmptyContent,
		NcLoadingIcon,
		PageIcon,
		RestoreIcon,
	},

	props: {
		pageId: {
			type: Number,
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

		...mapActions({
			dispatchGetVersions: GET_VERSIONS,
		}),

		/**
		 * Get versions of a page
		 */
		async getPageVersions() {
			this.load('versions')
			try {
				this.dispatchGetVersions(this.pageId)
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

<style lang="scss" scoped>
// Copied from apps/files_versions/src/css/versions.css
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
	left: 12px;

	&.item-icon-page {
		display: flex;
		height: 34px;
		background-color: var(--color-background-darker);
		border-radius: 4px;
	}

	&.item-icon-loading {
		padding-top: 6px;
	}
}

.app-content-list .app-content-list-item .app-content-list-item-line-one {
	font-size: 120%;
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
