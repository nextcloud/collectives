<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="!staticSites.length" class="publish-website-list publish-website-list--empty">
		<span class="publish-website-list__empty-icon">
			<WebIcon :size="48" />
		</span>
		<h3 class="publish-website-list__empty-title">
			{{ t('collectives', 'No website online yet') }}
		</h3>
		<p class="publish-website-list__empty-description">
			{{ t('collectives', 'Publish selected pages of this collective as a static website that anyone with the link can read. You can create several websites, each with its own address.') }}
		</p>
		<ol class="publish-website-list__steps">
			<li v-for="(step, index) in steps" :key="index" class="publish-website-list__step">
				<span class="publish-website-list__step-number">{{ index + 1 }}</span>
				<strong>{{ step.title }}</strong>
				<span class="publish-website-list__step-description">{{ step.description }}</span>
			</li>
		</ol>
		<NcButton variant="primary" @click="$emit('create')">
			<template #icon>
				<PlusIcon :size="20" />
			</template>
			{{ t('collectives', 'Create website') }}
		</NcButton>
	</div>
	<div v-else class="publish-website-list">
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
						:class="`publish-website-list__badge--${badgeState(staticSite)}`">
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
					{{ n('collectives', '%n page', '%n pages', publishedOrSelectedPageIds(staticSite).length) }}
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
import WebIcon from 'vue-material-design-icons/Web.vue'
import { hasUnpublishedChanges, mostRecentlyUpdatedStaticSite, staticSiteFailureReason, staticSiteState } from '../../util/staticSiteStatus.js'

export default {
	name: 'PublishWebsiteList',

	components: {
		NcButton,
		NcLoadingIcon,
		PlusIcon,
		WebIcon,
	},

	props: {
		/** Static sites of the collective in display order */
		staticSites: {
			type: Array,
			required: true,
		},

		/** All pages of the collective, to detect changes after the last publication */
		pages: {
			type: Array,
			required: true,
		},
	},

	emits: [
		'create',
		'edit',
	],

	computed: {
		// Only one publication or deletion per collective may run at a time
		isLocked() {
			return this.staticSites.some((staticSite) => staticSite.isInProgress)
		},

		// The website the user most likely wants to continue with, e.g. after a failed publication
		preselectedStaticSite() {
			return mostRecentlyUpdatedStaticSite(this.staticSites)
		},

		steps() {
			return [
				{
					title: t('collectives', 'Choose pages'),
					description: t('collectives', 'Pick which pages and subpages go public.'),
				},
				{
					title: t('collectives', 'Set address and look'),
					description: t('collectives', 'A permanent address, a title and a theme.'),
				},
				{
					title: t('collectives', 'Update anytime'),
					description: t('collectives', 'Edit pages as usual, then update the website in one click.'),
				},
			]
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
		 * Pages of the version that is online, as the publication time is shown next to them
		 *
		 * @param {object} staticSite static site as returned by the backend
		 * @return {number[]} IDs of the pages online, or selected for the first publication
		 */
		publishedOrSelectedPageIds(staticSite) {
			return staticSite.publishedSelectedPageIds ?? staticSite.selectedPageIds
		},

		/**
		 * @param {object} staticSite static site as returned by the backend
		 * @return {'publishing'|'live'|'outdated'|'failed'} state shown in the badge
		 */
		badgeState(staticSite) {
			const state = staticSiteState(staticSite)
			return state === 'live' && hasUnpublishedChanges(staticSite, this.pages)
				? 'outdated'
				: state
		},

		/**
		 * @param {object} staticSite static site as returned by the backend
		 * @return {string} translated state of the last publication
		 */
		stateLabel(staticSite) {
			switch (this.badgeState(staticSite)) {
				case 'publishing':
					return t('collectives', 'Publishing')
				case 'live':
					return t('collectives', 'Live · up to date')
				case 'outdated':
					return t('collectives', 'Live · changes not published')
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

	&--empty {
		container-type: inline-size;
		align-items: center;
		overflow-y: auto;
		text-align: center;
	}

	&__empty-icon {
		display: flex;
		color: var(--color-primary-element);
	}

	&__empty-title {
		margin: 0;
		font-size: 18px;
	}

	&__empty-description {
		max-width: 500px;
		color: var(--color-text-maxcontrast);
	}

	&__steps {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 12px;
		width: 100%;
		margin: 0;
		padding: 0;
	}

	@container (width < 500px) {
		&__steps {
			grid-template-columns: 1fr;
		}
	}

	&__step {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 4px;
		list-style: none;
		padding: 12px 16px;
		border: 1px solid var(--color-border-dark);
		border-radius: var(--border-radius-large);
		text-align: start;
	}

	&__step-number {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 24px;
		height: 24px;
		border-radius: 50%;
		background-color: var(--color-primary-element);
		color: var(--color-primary-element-text);
		font-size: var(--font-size-small, 13px);
		font-weight: bold;
	}

	&__step-description {
		color: var(--color-text-maxcontrast);
	}

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
		border: 1px solid var(--color-border-dark);
		border-radius: var(--border-radius-large);

		&--preselected {
			// Thicker border without shifting the content
			padding: 11px 15px;
			border: 2px solid var(--color-primary-element);
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

		&--outdated {
			border-color: var(--color-warning-text);
			background-color: var(--color-warning);
			color: var(--color-warning-text);
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
