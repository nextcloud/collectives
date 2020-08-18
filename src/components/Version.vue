<template>
	<AppContent>
		<div>
			<div id="action-menu">
				<Actions>
					<ActionButton
						icon="icon-history"
						@click="revertVersion">
						{{ t('wiki', 'Restore this version') }}
					</ActionButton>
				</Actions>
			</div>
			<h1 class="page-title">
				{{ page.title }}
			</h1>
			<RichText :page-id="page.id"
				:page-url="pageUrl"
				:is-version="true" />
		</div>
	</AppContent>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import RichText from './RichText'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateRemoteUrl } from '@nextcloud/router'

export default {
	name: 'Version',

	components: {
		ActionButton,
		Actions,
		AppContent,
		RichText,
	},

	props: {
		version: {
			type: Object,
			required: false,
			default: null,
		},
		currentVersionTimestamp: {
			type: Number,
			required: true,
		},
	},

	computed: {

		page() {
			return this.$store.getters.currentPage
		},

		/**
		 * Return the URL for currently selected page version
		 * @returns {string}
		 */
		pageUrl() {
			return this.version.downloadUrl
		},

		/**
		 * @returns {string}
		 */
		restoreFolderUrl() {
			return generateRemoteUrl(
				`dav/versions/${this.getUser}/restore/${this.page.id}`
			)
		},

		/**
		 * @returns {string}
		 */
		getUser() {
			return getCurrentUser().uid
		},
	},

	methods: {
		/**
		 * Revert page to an old version
		 */
		async revertVersion() {
			try {
				await axios({
					method: 'MOVE',
					url: this.version.downloadUrl,
					headers: {
						'Destination': this.restoreFolderUrl,
					},
				})
				this.$emit('resetVersion')
				showSuccess(t('wiki', 'Reverted {page} to revision {timestamp}.', {
					page: this.page.title,
					timestamp: this.version.relativeTimestamp,
				}))
			} catch (e) {
				showError(t('wiki', 'Failed to revert {page} to revision {timestamp}.', {
					page: this.page.title,
					timestamp: this.version.relativeTimestamp,
				}))
				console.error('Failed to move page to restore folder', e)
			}
		},
	},
}
</script>

<style scoped>
	.page-title {
		padding: 4px 8px 2px 14px;
	}
</style>
