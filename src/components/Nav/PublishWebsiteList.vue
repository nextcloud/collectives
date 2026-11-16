<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="publish-website-list">
		<div class="publish-website-list__header">
			<span class="publish-website-list__count">
				{{ countLabel }}
			</span>
			<NcButton
				variant="primary"
				:disabled="isLocked"
				@click="$emit('create')">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('collectives', 'New website') }}
			</NcButton>
		</div>
		<ul class="publish-website-list__websites">
			<li
				v-for="staticSite in staticSites"
				:key="staticSite.id"
				class="publish-website-list__website"
				:class="{ 'publish-website-list__website--preselected': staticSite.id === preselectedStaticSite?.id }">
				<div class="publish-website-list__website-header">
					<strong class="publish-website-list__title">{{ staticSite.title }}</strong>
					<span
						class="publish-website-list__badge"
						:class="`publish-website-list__badge--${staticSiteState(staticSite)}`">
						<NcLoadingIcon v-if="staticSiteState(staticSite) === 'publishing'" :size="16" />
						{{ stateLabel(staticSite) }}
					</span>
					<NcButton
						:ref="staticSite.id === preselectedStaticSite?.id ? 'preselectedEditButton' : undefined"
						class="publish-website-list__edit"
						:variant="staticSite.id === preselectedStaticSite?.id ? 'primary' : 'secondary'"
						:aria-label="t('collectives', 'Edit website {title}', { title: staticSite.title })"
						:disabled="isLocked"
						@click="$emit('edit', staticSite)">
						{{ t('collectives', 'Edit') }}
					</NcButton>
				</div>
				<a
					v-if="staticSite.publishedUrl"
					class="publish-website-list__url"
					:href="staticSite.publishedUrl"
					target="_blank"
					rel="noopener noreferrer">{{ staticSite.publishedUrl }}</a>
				<p v-if="staticSiteState(staticSite) === 'failed'" class="publish-website-list__error">
					{{ staticSiteFailureReason(staticSite) }}
					{{ staticSite.publishedUrl
						? t('collectives', 'The previous version is still online.')
						: t('collectives', 'The website is not online.') }}
				</p>
				<span class="publish-website-list__details">
					{{ n('collectives', '%n page', '%n pages', staticSite.selectedPageIds.length) }}
					·
					<span
						v-if="staticSite.publishedAt"
						:title="formatAbsoluteTime(staticSite.publishedAt)">
						{{ t('collectives', 'Published {time}', { time: formatRelativeTime(staticSite.publishedAt) }) }}
					</span>
					<template v-else>
						{{ t('collectives', 'Not published yet') }}
					</template>
				</span>
			</li>
		</ul>
	</div>
</template>

<script>
import { n, t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { mostRecentlyUpdatedStaticSite, staticSiteFailureReason, staticSiteState } from '../../util/staticSiteStatus.js'

export default {
	name: 'PublishWebsiteList',

	components: {
		NcButton,
		NcLoadingIcon,
		PlusIcon,
	},

	props: {
		/** Static sites of the collective in display order */
		staticSites: {
			type: Array,
			required: true,
		},
	},

	emits: [
		'create',
		'edit',
	],

	computed: {
		// Only one publication per collective may run at a time
		isLocked() {
			return this.staticSites.some((staticSite) => staticSiteState(staticSite) === 'publishing')
		},

		// The website the user most likely wants to continue with, e.g. after a failed publication
		preselectedStaticSite() {
			return mostRecentlyUpdatedStaticSite(this.staticSites)
		},

		countLabel() {
			const liveCount = this.staticSites.filter((staticSite) => staticSite.publishedUrl).length
			return n('collectives', '%n website', '%n websites', this.staticSites.length)
				+ ' · '
				+ t('collectives', '{count} live', { count: liveCount })
		},
	},

	mounted() {
		this.$refs.preselectedEditButton?.[0]?.$el.focus()
	},

	methods: {
		n,
		t,
		staticSiteFailureReason,
		staticSiteState,

		/**
		 * @param {object} staticSite static site as returned by the backend
		 * @return {string} translated state of the last publication
		 */
		stateLabel(staticSite) {
			switch (staticSiteState(staticSite)) {
				case 'publishing':
					return t('collectives', 'Publishing')
				case 'live':
					return t('collectives', 'Live')
				default:
					return t('collectives', 'Failed')
			}
		},

		formatRelativeTime(timestamp) {
			return moment.unix(timestamp).fromNow()
		},

		formatAbsoluteTime(timestamp) {
			return moment.unix(timestamp).format('LLL')
		},
	},
}
</script>

<style lang="scss" scoped>
.publish-website-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-height: 0;

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
	}

	&__count,
	&__details {
		color: var(--color-text-maxcontrast);
	}

	&__websites {
		display: flex;
		flex-direction: column;
		gap: 8px;
		margin: 0;
		padding: 0;
		overflow-y: auto;
	}

	&__website {
		display: flex;
		flex-direction: column;
		gap: 4px;
		list-style: none;
		padding: 12px 16px;
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius-large);

		&--preselected {
			border-color: var(--color-primary-element);
		}
	}

	&__website-header {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__title {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__badge {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		flex: 0 0 auto;
		padding: 0 8px;
		border: 1px solid;
		border-radius: var(--border-radius-pill);
		font-size: var(--font-size-small, 13px);

		&--live {
			border-color: var(--color-success-text);
			background-color: var(--color-success);
			color: var(--color-success-text);
		}

		&--publishing {
			border-color: var(--color-primary-element-light-text);
			background-color: var(--color-primary-element-light);
			color: var(--color-primary-element-light-text);
		}

		&--failed {
			border-color: var(--color-error-text);
			background-color: var(--color-error);
			color: var(--color-error-text);
		}
	}

	&__edit {
		margin-inline-start: auto;
	}

	&__url {
		color: var(--color-primary-element);
		text-decoration: underline;
		word-break: break-all;
	}

	&__error {
		color: var(--color-error-text);
	}
}
</style>
