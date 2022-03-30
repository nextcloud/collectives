<template>
	<div>
		<h1 id="titleform" class="page-title">
			<input class="title"
				type="text"
				disabled
				:value="versionTitle">
			<button class="restore-button warn"
				:title="t('collectives', 'Restore this version')"
				@click="revertVersion">
				<span class="icon icon-history" />
				{{ t('collectives', 'Restore') }}
			</button>
			<Actions>
				<ActionButton icon="icon-menu-sidebar"
					:close-after-click="true"
					@click="closeVersions" />
			</Actions>
		</h1>
		<div id="text-container">
			<RichText :page-id="page.id"
				:page-url="pageUrl"
				:timestamp="page.timestamp"
				:is-version="true" />
		</div>
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import RichText from './RichText'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateRemoteUrl } from '@nextcloud/router'
import { mapGetters, mapMutations } from 'vuex'
import { SELECT_VERSION } from '../../store/mutations'
import { GET_VERSIONS } from '../../store/actions'

export default {
	name: 'Version',

	components: {
		ActionButton,
		Actions,
		RichText,
	},

	computed: {
		...mapGetters({
			page: 'currentPage',
			collective: 'currentCollective',
			version: 'version',
			title: 'title',
		}),

		/**
		 * Return the URL for currently selected page version
		 *
		 * @return {string}
		 */
		pageUrl() {
			return this.version.downloadUrl
		},

		/**
		 * @return {string}
		 */
		restoreFolderUrl() {
			return generateRemoteUrl(
				`dav/versions/${this.getUser}/restore/${this.page.id}`
			)
		},

		/**
		 * @return {string}
		 */
		getUser() {
			return getCurrentUser().uid
		},

		versionTitle() {
			return `${this.title} (${this.version.relativeTimestamp})`
		},
	},

	methods: {
		...mapMutations(['hide']),
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
				this.$store.dispatch(GET_VERSIONS, this.page.id)
				showSuccess(t('collectives', 'Reverted {page} to revision {timestamp}.', {
					page: this.page.title,
					timestamp: target.relativeTimestamp,
				}))
			} catch (e) {
				showError(t('collectives', 'Failed to revert {page} to revision {timestamp}.', {
					page: this.page.title,
					timestamp: target.relativeTimestamp,
				}))
				console.error('Failed to move page to restore folder', e)
			}
		},
	},
}
</script>

<style lang="scss">
.restore-button {
	min-width: max-content;
	height: 44px;

	.icon {
		opacity: 1;
		margin-right: 8px;
	}
}
</style>
