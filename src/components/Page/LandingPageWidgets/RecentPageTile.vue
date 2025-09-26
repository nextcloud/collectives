<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<router-link
		:to="pagePath(page)"
		class="recent-page-tile"
		:class="{ dark: isDarkTheme }">
		<div class="recent-page-tile__icon">
			<div v-if="emoji" class="recent-page-tile__emoji">
				{{ emoji }}
			</div>
			<PageIcon v-else :size="36" fill-color="var(--color-background-darker)" />
		</div>
		<div class="recent-page-tile__text">
			<div class="recent-page-tile__title">
				{{ title }}
			</div>
			<div class="recent-page-tile__subtitle">
				<NcAvatar
					:user="page.lastUserId || ''"
					:display-name="page.lastUserDisplayName || ''"
					:size="24" />
				<span class="timestamp">
					{{ lastUpdate }}
				</span>
			</div>
		</div>
	</router-link>
</template>

<script>
import moment from '@nextcloud/moment'
import { NcAvatar } from '@nextcloud/vue'
import { isDarkTheme } from '@nextcloud/vue/functions/isDarkTheme'
import { mapState } from 'pinia'
import PageIcon from '../../Icon/PageIcon.vue'
import { useCollectivesStore } from '../../../stores/collectives.js'
import { usePagesStore } from '../../../stores/pages.js'

export default {
	name: 'RecentPageTile',
	components: {
		NcAvatar,
		PageIcon,
	},

	props: {
		page: {
			type: Object,
			required: true,
		},
	},

	computed: {
		...mapState(useCollectivesStore, ['currentCollective']),
		...mapState(usePagesStore, ['pagePath']),

		isDarkTheme() {
			return isDarkTheme
		},

		isLandingPage() {
			return this.page.parentId === 0
		},

		emoji() {
			return (this.isLandingPage && this.currentCollective.emoji)
				? this.currentCollective.emoji
				: this.page.emoji
		},

		title() {
			return this.isLandingPage
				? this.currentCollective.name
				: this.page.title
		},

		lastUpdate() {
			return moment.unix(this.page.timestamp).fromNow()
		},
	},
}
</script>

<style lang="scss" scoped>
.recent-page-tile {
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: space-between;
	height: 144px;
	width: 144px;

	scroll-snap-align: start;
	border-radius: var(--border-radius-large);
	box-shadow: 0 0 4px 0 var(--color-box-shadow);

	padding: 8px;

	&:hover {
		box-shadow: 0 0 8px 0 var(--color-box-shadow);
	}

	&.dark {
		background-color: var(--color-background-dark);

		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	&__icon {
		display: flex;
		height: 72px;
		width: 144px;
		align-items: center;
		justify-content: flex-start;
	}

	&__text {
		height: 100%;
		display: flex;
		margin-top: 8px;
		flex-direction: column;
	}

	&__emoji {
		font-size: 29px;
	}

	&__title {
		font-weight: bold;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}

	&__subtitle {
		display: flex;
		gap: 4px;
		margin-top: auto;
		align-items: center;

		.timestamp {
			color: var(--color-text-maxcontrast);
			margin-left: 2px;

			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
	}
}
</style>
