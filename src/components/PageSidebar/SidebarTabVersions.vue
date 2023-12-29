<template>
	<div class="versions-container">
		<!-- loading -->
		<NcEmptyContent v-if="loading('versions')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- error message -->
		<NcEmptyContent v-else-if="error" :name="error">
			<template #icon>
				<AlertOctagonIcon />
			</template>
		</NcEmptyContent>

		<!-- versions list -->
		<div v-else-if="!loading('versions') && versions.length">
			<ul class="version-list">
				<span :title="pageFormattedTimestamp">
					<NcListItem :name="t('collectives', 'Current version')"
						class="version"
						:class="{'active': !version}"
						:active="!version"
						@click="clickPreviewVersion(null)">
						<template #icon>
							<PageIcon :size="26"
								fill-color="var(--color-main-background)"
								class="item-icon item-icon__page" />
						</template>
						<template #subname>
							{{ pageHumanReadableSize }}
						</template>
					</NcListItem>
				</span>
				<span v-for="v in versions"
					:key="v.downloadUrl"
					:title="v.formattedTimestamp">
					<NcListItem :name="v.relativeTimestamp"
						class="version"
						:class="{'active': selected(v)}"
						:active="selected(v)"
						@click="clickPreviewVersion(v)">
						<template #icon>
							<NcLoadingIcon v-if="loading(`version-${pageId}-${v.timestamp}`)"
								:size="26"
								fill-color="var(--color-main-background)"
								class="item-icon item-icon__loading" />
							<PageIcon v-else
								:size="26"
								fill-color="var(--color-main-background)"
								class="item-icon item-icon__page" />
						</template>
						<template #subname>
							{{ v.humanReadableSize }}
						</template>
					</NcListItem>
				</span>
			</ul>
		</div>

		<!-- no versions found -->
		<NcEmptyContent v-else
			:name="t('collectives', 'No other versions available')"
			:description="t( 'collectives', 'After editing you can find old versions of the page here.')">
			<template #icon>
				<RestoreIcon />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { formatFileSize } from '@nextcloud/files'
import { NcEmptyContent, NcListItem, NcLoadingIcon } from '@nextcloud/vue'
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
		NcEmptyContent,
		NcListItem,
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

		selected() {
			return (v) => {
				return v.timestamp === this.version?.timestamp
			}
		},
	},

	watch: {
		'pageId'() {
			this.load('versions')
			this.getPageVersions()
		},
	},

	beforeMount() {
		this.load('versions')
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
			try {
				await this.dispatchGetVersions(this.pageId)
			} catch (e) {
				this.error = t('collectives', 'Could not get page versions')
				console.error('Failed to get page versions', e)
			} finally {
				this.done('versions')
			}
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
.version {
	display: flex;
	flex-direction: row;

	:deep(.line-one__name) {
		font-weight: normal;
	}

	.item-icon {
		height: 34px;
		border-radius: var(--border-radius);

		&__page {
			background-color: var(--color-background-darker);
		}
	}
}
</style>
