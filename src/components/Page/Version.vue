<template>
	<div>
		<h1 id="titleform" class="page-title" :class="[isFullWidthView ? 'full-width-view' : 'sheet-view']">
			<div class="page-title-icon">
				<div v-if="currentPage.emoji">
					{{ currentPage.emoji }}
				</div>
				<EmoticonOutlineIcon v-else
					class="emoji-picker-emoticon"
					:size="pageTitleIconSize"
					fill-color="var(--color-text-maxcontrast)" />
			</div>

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
		<SkeletonLoading v-show="!contentLoaded" class="page-content-skeleton" type="text" />
		<div v-show="contentLoaded"
			id="text-container"
			:class="[isFullWidthView ? 'full-width-view' : 'sheet-view']">
			<div v-if="useEditorApi"
				ref="reader"
				data-collectives-el="reader" />
			<Reader v-else
				:key="`show-${currentPage.id}-${version.timestamp}`"
				:current-page="currentPage"
				:page-content="davContent" />
		</div>
	</div>
</template>

<script>
import { NcActionButton, NcActions, NcButton } from '@nextcloud/vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import Reader from './Reader.vue'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateRemoteUrl } from '@nextcloud/router'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import EmoticonOutlineIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import { SELECT_VERSION } from '../../store/mutations.js'
import { GET_VERSIONS } from '../../store/actions.js'
import editorMixin from '../../mixins/editorMixin.js'
import pageContentMixin from '../../mixins/pageContentMixin.js'
import SkeletonLoading from '../SkeletonLoading.vue'

export default {
	name: 'Version',

	components: {
		EmoticonOutlineIcon,
		NcActionButton,
		NcActions,
		NcButton,
		RestoreIcon,
		Reader,
		SkeletonLoading,
	},

	mixins: [
		editorMixin,
		isMobile,
		pageContentMixin,
	],

	computed: {
		...mapGetters([
			'currentPage',
			'isFullWidthView',
			'loading',
			'useEditorApi',
			'title',
			'version',
		]),

		pageTitleIconSize() {
			return isMobile ? 25 : 30
		},

		restoreFolderUrl() {
			return generateRemoteUrl(
				`dav/versions/${this.getUser}/restore/${this.currentPage.id}`,
			)
		},

		getUser() {
			return getCurrentUser().uid
		},

		versionTitle() {
			return `${this.title} (${this.version.relativeTimestamp})`
		},

		contentLoaded() {
			return !!this.davContent || !this.loading(`version-${this.currentPage.id}-${this.version.timestamp}`)
		},
	},

	watch: {
		'version.timestamp'() {
			this.davContent = ''
			this.getPageContent()
		},
	},

	mounted() {
		if (this.useEditorApi) {
			this.pageInfoBarPage = {}
			this.setupReader()
		}
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
			this.davContent = await this.fetchPageContent(this.version.downloadUrl)
			this.reader?.setContent(this.davContent)
			this.done(`version-${this.currentPage.id}-${this.version.timestamp}`)
		},
	},
}
</script>

<style lang="scss" scoped>
.page-content-skeleton {
	padding-top: 44px;
}
</style>
