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
					@click="$emit('showCurrent'); hide('sidebar')">
					{{ t('collectives', 'Close sidebar') }}
				</ActionButton>
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

export default {
	name: 'Version',

	components: {
		ActionButton,
		Actions,
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
		...mapGetters({
			page: 'currentPage',
			collective: 'currentCollective',
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

		landingPage() {
			return !this.$store.getters.pageParam
		},

		versionTitle() {
			const title = this.landingPage ? this.collective.name : this.page.title
			return `${title} (${this.version.relativeTimestamp})`
		},
	},

	methods: {
		...mapMutations(['hide']),
		/**
		 * Revert page to an old version
		 */
		async revertVersion() {
			try {
				await axios({
					method: 'MOVE',
					url: this.version.downloadUrl,
					headers: {
						Destination: this.restoreFolderUrl,
					},
				})
				this.$emit('resetVersion')
				showSuccess(t('collectives', 'Reverted {page} to revision {timestamp}.', {
					page: this.page.title,
					timestamp: this.version.relativeTimestamp,
				}))
			} catch (e) {
				showError(t('collectives', 'Failed to revert {page} to revision {timestamp}.', {
					page: this.page.title,
					timestamp: this.version.relativeTimestamp,
				}))
				console.error('Failed to move page to restore folder', e)
			}
		},
	},
}
</script>
