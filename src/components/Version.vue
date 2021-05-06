<template>
	<div>
		<h1 id="titleform" class="page-title">
			<input class="title"
				type="text"
				disabled
				:value="versionTitle">
			<button
				type="button"
				class="button warn"
				:title="t('collectives', 'Restore this version')"
				@click="revertVersion">
				<span class="icon icon-history" />
				{{ t('collectives', 'Restore') }}
			</button>
			<Actions>
				<ActionButton
					icon="icon-menu"
					:close-after-click="true"
					@click="$emit('showCurrent'); hide('sidebar')" />
			</Actions>
		</h1>
		<RichText :page-id="page.id"
			:page-url="pageUrl"
			:is-version="true" />
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
import { SELECT_VERSION } from '../store/mutations'

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

		versionTitle() {
			return `${this.title} (${this.version.relativeTimestamp})`
		},
	},

	methods: {
		...mapMutations(['hide']),
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
