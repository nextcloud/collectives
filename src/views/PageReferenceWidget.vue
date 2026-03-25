<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<!-- handle inaccessible pages -->
	<a
		v-if="richObject && notFound"
		:href="richObject.link"
		target="_blank"
		class="collective-page not-found">
		<div class="collective-page--image">
			<PageIcon
				:size="50" />
		</div>
		<div class="collective-page--info">
			<div class="title">
				<strong>
					{{ t('collectives', 'Page not found') }}
				</strong>
			</div>
			<div class="description">
				{{ t('collectives', 'The page does not exist or you are not allowed to view it.') }}
			</div>
		</div>
	</a>
	<!-- handle accessible pages -->
	<a
		v-else-if="richObject"
		:href="richObject.link"
		target="_blank"
		class="collective-page"
		@click="clickLink">
		<div class="collective-page--image">
			<span
				v-if="emoji"
				class="page-emoji">
				{{ emoji }}
			</span>
			<PageIcon
				v-else
				:size="50" />
		</div>
		<div class="collective-page--info">
			<div class="title">
				<strong>
					{{ richObject.page.title }}
				</strong>
			</div>
			<div class="description">
				{{ richObject.description }}
			</div>
			<div class="last-edited">
				{{ richObject.lastEdited }}
				<NcUserBubble
					:user="richObject.page.lastUserId"
					:displayName="richObject.page.lastUserDisplayName" />
			</div>
		</div>
	</a>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import PageIcon from '../components/Icon/PageIcon.vue'

export default {
	name: 'PageReferenceWidget',

	components: {
		PageIcon,
		NcUserBubble,
	},

	/* eslint-disable vue/no-unused-properties */
	/* eslint-disable vue/no-boolean-default */
	props: {
		richObjectType: {
			type: String,
			default: '',
		},

		richObject: {
			type: Object,
			default: null,
		},

		accessible: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		emoji() {
			return this.richObject.page?.emoji
		},

		notFound() {
			return this.accessible === false
		},
	},

	methods: {
		t,

		clickLink(event) {
			const appUrl = '/apps/collectives'
			const linkUrl = new URL(this.richObject.link, window.location)
			// Only consider rerouting if we're inside the collectives app and for links to collectives app
			if (window.OCA.Collectives?.vueRouter
				&& linkUrl.pathname.toString().startsWith(generateUrl(appUrl))) {
				event.preventDefault()
				const collectivesUrl = linkUrl.href.substring(linkUrl.href.indexOf(appUrl) + appUrl.length)
				window.OCA.Collectives.vueRouter.push(collectivesUrl)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.collective-page {
	width: 100%;
	white-space: normal;
	padding: 12px !important;
	display: flex;
	text-decoration: unset !important;
	color: var(--color-main-text) !important;

	&--image {
		margin-inline-end: 12px;
		display: flex;
		align-items: center;
		.page-emoji {
			display: flex;
			align-items: center;
			height: 50px;
			font-size: 50px;
		}
	}

	&.not-found {
		.description,
		.collective-page--image {
			color: var(--color-text-maxcontrast) !important
		}
	}

	.spacer {
		flex-grow: 1;
	}
}
</style>
