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
		:style="pageStyle"
		@click="clickLink">
		<div class="collective-page--image" :style="imageStyle">
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
			default: null,
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

		pageStyle() {
			const paddingInline = this.small ? 8 : 12
			return {
				'padding-inline': `${paddingInline}px !important`,
			}
		},

		imageStyle() {
			return {
				width: `${this.iconSize}px`,
			}
		},

		emojiStyle() {
			return {
				'font-size': `calc(${this.iconSize}px * 0.8)`,
				height: `${this.iconSize}px`,
			}
		},
	},

	methods: {
		t,

		clickLink(event: Event) {
			if (!this.link) {
				return false
			}

			if (window.OCA.Collectives?.openLink) {
				event.preventDefault()
				window.OCA.Collectives.openLink(this.link)
			}
		},
	},
})

</script>

<style scoped lang="scss">
.collective-page {
	width: 100%;
	white-space: normal;
	padding-block: 12px !important;
	display: flex;
	text-decoration: unset !important;
	color: var(--color-main-text) !important;

	&--image {
		margin-inline-end: 12px;
		display: flex;
		align-items: center;
		justify-content: center;

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
