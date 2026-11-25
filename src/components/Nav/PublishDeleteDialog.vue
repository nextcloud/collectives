<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		class="publish-delete-dialog"
		:name="name"
		:buttons="buttons"
		@closing="$emit('close')">
		<!-- eslint-disable-next-line vue/no-v-html -- translation is sanitized, the URL is escaped -->
		<p v-if="staticSite.publishedUrl" class="publish-delete-dialog__text" v-html="stopWorkingText" />
		<p v-else class="publish-delete-dialog__text">
			{{ t('collectives', 'The settings of the website are deleted. This can\'t be undone.') }}
		</p>
		<p class="publish-delete-dialog__text">
			{{ t('collectives', 'The pages in this collective are not affected.') }}
		</p>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'
import escapeHtml from 'escape-html'
import { mapActions, mapState } from 'pinia'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { useStaticSitesStore } from '../../stores/staticSites.js'
import displayError from '../../util/displayError.js'

export default {
	name: 'PublishDeleteDialog',

	components: {
		NcDialog,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},

		/** Static site to delete */
		staticSite: {
			type: Object,
			required: true,
		},
	},

	emits: [
		'close',
	],

	computed: {
		...mapState(useStaticSitesStore, ['staticSitesForCollective']),

		name() {
			return t('collectives', 'Delete website "{title}"?', { title: this.staticSite.title })
		},

		stopWorkingText() {
			return t('collectives', '{url} will stop working. The settings of the website are deleted as well. This can\'t be undone.', {
				url: { value: `<strong>${escapeHtml(this.staticSite.publishedUrl)}</strong>`, escape: false },
			})
		},

		buttons() {
			return [
				{
					label: t('collectives', 'Cancel'),
					variant: 'secondary',
				},
				{
					label: t('collectives', 'Delete website'),
					variant: 'error',
					callback: () => this.onDelete(),
				},
			]
		},
	},

	methods: {
		t,

		...mapActions(useStaticSitesStore, ['deleteStaticSite', 'getStaticSites', 'watchDeletion']),

		/**
		 * The dialog stays open after an error if deleting can be tried again right away.
		 *
		 * @return {Promise<boolean|undefined>} false to keep the dialog open
		 */
		async onDelete() {
			try {
				await this.deleteStaticSite(this.collective, this.staticSite)
				return
			} catch (e) {
				displayError('Could not delete website')(e)
			}

			// The website may have changed meanwhile, e.g. by another member of the collective
			try {
				await this.getStaticSites(this.collective.id)
			} catch (e) {
				displayError('Could not load static sites for collective')(e)
				return false
			}
			const staticSite = this.staticSitesForCollective(this.collective.id).find(({ id }) => id === this.staticSite.id)
			if (!staticSite) {
				return
			}
			// The publish service may have got the request although it wasn't reachable
			if (staticSite.status === 'deleting' && staticSite.isInProgress) {
				this.watchDeletion(this.collective, staticSite)
				return
			}
			return false
		},
	},
}
</script>

<style lang="scss" scoped>
.publish-delete-dialog__text {
	margin-block-end: 8px;
}
</style>

<style lang="scss">
// The dialog is mounted to the body, outside of scoped styles. A long name must not run below the close button.
.publish-delete-dialog .dialog__name {
	margin-inline: var(--default-clickable-area);
}
</style>
