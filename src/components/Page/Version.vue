<template>
	<div>
		<h1 id="titleform" class="page-title">
			<input class="title"
				:class="{ 'mobile': isMobile }"
				type="text"
				disabled
				:value="versionTitle">
			<NcButton v-tooltip="t('collectives', 'Restore this version')"
				:aria-label="t('collectives', 'Restore this version')"
				class="titleform-button"
				@click="revertVersion">
				<template #icon>
					<RestoreIcon :size="20" />
				</template>
				{{ t('collectives', 'Restore') }}
			</NcButton>
			<NcActions>
				<NcActionButton icon="icon-menu-sidebar" :close-after-click="true" @click="closeVersions" />
			</NcActions>
		</h1>
		<div id="text-container">
			<RichText :key="`show-${currentPage.id}-${version.timestamp}`"
				:current-page="currentPage"
				:page-content="pageVersionContent" />
		</div>
	</div>
</template>

<script>
import { NcActionButton, NcActions, NcButton } from '@nextcloud/vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import RichText from './RichText.vue'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateRemoteUrl } from '@nextcloud/router'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { SELECT_VERSION } from '../../store/mutations.js'
import { GET_VERSIONS } from '../../store/actions.js'
import pageContentMixin from '../../mixins/pageContentMixin.js'

export default {
	name: 'Version',

	components: {
		NcActionButton,
		NcActions,
		NcButton,
		RestoreIcon,
		RichText,
	},

	mixins: [
		isMobile,
		pageContentMixin,
	],

	data() {
		return {
			pageVersionContent: '',
		}
	},

	computed: {
		...mapGetters([
			'currentPage',
			'version',
			'title',
		]),

		restoreFolderUrl() {
			return generateRemoteUrl(
				`dav/versions/${this.getUser}/restore/${this.currentPage.id}`
			)
		},

		getUser() {
			return getCurrentUser().uid
		},

		versionTitle() {
			return `${this.title} (${this.version.relativeTimestamp})`
		},
	},

	watch: {
		'version.timestamp'() {
			this.getPageContent()
		},
	},

	mounted() {
		this.getPageContent()
	},

	methods: {
		...mapMutations(['done', 'load', 'hide']),

		...mapActions({
			dispatchGetVersions: GET_VERSIONS,
		}),

		closeVersions() {
			this.$store.commit(SELECT_VERSION, null)
			this.hide('sidebar')
		},
		/**
		 * Revert page to an old version
		 */
		async revertVersion() {
			const target = this.version
			try {
				await axios({
					method: 'MOVE',
					url: target.downloadUrl,
					headers: {
						Destination: this.restoreFolderUrl,
					},
				})
				this.$store.commit(SELECT_VERSION, null)
				this.dispatchGetVersions(this.currentPage.id)
				showSuccess(t('collectives', 'Reverted {page} to revision {timestamp}.', {
					page: this.currentPage.title,
					timestamp: target.relativeTimestamp,
				}))
			} catch (e) {
				showError(t('collectives', 'Failed to revert {page} to revision {timestamp}.', {
					page: this.currentPage.title,
					timestamp: target.relativeTimestamp,
				}))
				console.error('Failed to move page to restore folder', e)
			}
		},

		async getPageContent() {
			this.load(`version-${this.currentPage.id}-${this.version.timestamp}`)
			this.pageVersionContent = await this.fetchPageContent(this.version.downloadUrl)
			this.done(`version-${this.currentPage.id}-${this.version.timestamp}`)
		},
	},
}
</script>
