<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<a
		:href="link"
		target="_blank"
		class="collective-page"
		:class="{ 'not-found': notFound }"
		@click="clickLink">
		<div class="collective-page--image">
			<span
				v-if="emoji"
				class="page-emoji"
				:style="emojiStyle">
				{{ emoji }}
			</span>
			<PageIcon
				v-else
				:size="iconSize" />
		</div>
		<div class="collective-page--info">
			<div class="title">
				<strong>
					<template v-if="notFound">
						{{ t('collectives', 'Page not found') }}
					</template>
					<template v-else>
						{{ title }}
					</template>
				</strong>
			</div>
			<div class="description">
				<template v-if="notFound">
					{{ t('collectives', 'The page does not exist or you are not allowed to view it.') }}
				</template>
				<template v-else>
					{{ description }}
				</template>
			</div>
			<div v-if="!notFound && !small" class="last-edited">
				{{ lastEdited }}
				<NcUserBubble
					:user="lastUserId"
					:displayName="lastUserDisplayName" />
			</div>
		</div>
	</a>
</template>

<script lang="ts">
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { defineComponent } from 'vue'
import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import PageIcon from './Icon/PageIcon.vue'

export default defineComponent({
	name: 'PagePreview',

	components: {
		NcUserBubble,
		PageIcon,
	},

	props: {
		notFound: {
			type: Boolean,
			default: false,
		},

		link: {
			type: String,
			required: true,
		},

		title: {
			type: String,
			required: true,
		},

		description: {
			type: String,
			required: true,
		},

		emoji: {
			type: String,
			default: '',
		},

		lastEdited: {
			type: String,
			default: '',
		},

		lastUserId: {
			type: String,
			default: '',
		},

		lastUserDisplayName: {
			type: String,
			default: '',
		},

		small: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		iconSize() {
			return this.small ? 34 : 50
		},

		emojiStyle() {
			return {
				height: `${this.iconSize}px`,
				width: `${this.iconSize}px`,
			}
		},
	},

	methods: {
		t,

		clickLink(event: Event) {
			if (this.notFound) {
				return false
			}

			const appUrl = '/apps/collectives'
			const linkUrl = new URL(this.link, window.location)
			// Only consider rerouting if we're inside the collectives app and for links to collectives app
			if (window.OCA.Collectives?.vueRouter
				&& linkUrl.pathname.toString().startsWith(generateUrl(appUrl))) {
				event.preventDefault()
				const collectivesUrl = linkUrl.href.substring(linkUrl.href.indexOf(appUrl) + appUrl.length)
				window.OCA.Collectives.vueRouter.push(collectivesUrl)
			}
		},
	},
})

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
		}
	}

	.description, .last-edited {
		color: var(--color-text-maxcontrast);
	}

	&.not-found {
		.collective-page--image {
			color: var(--color-text-maxcontrast);
		}
	}
}
</style>
