<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<router-link :to="pagePath(page)" class="recent-page-tile">
		<div class="recent-page-tile__icon">
			<div v-if="page.emoji" class="recent-page-tile__emoji">
				{{ page.emoji }}
			</div>
			<PageIcon v-else :size="36" fill-color="var(--color-text-maxcontrast)" />
		</div>
		<div class="recent-page-tile__text">
			<div class="recent-page-tile__title">
				{{ title }}
			</div>
			<div class="recent-page-tile__subtitle">
				<NcAvatar :user="page.lastUserId || ''"
					:display-name="page.lastUserDisplayName || ''"
					:size="20" />
				<span class="timestamp">
					{{ lastUpdate }}
				</span>
			</div>
		</div>
	</router-link>
</template>

<script>
import { mapState } from 'pinia'
import { usePagesStore } from '../../../stores/pages.js'
import { INDEX_PAGE } from '../../../constants.js'
import moment from '@nextcloud/moment'

import PageIcon from '../../Icon/PageIcon.vue'
import { NcAvatar } from '@nextcloud/vue'

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
		...mapState(usePagesStore, ['pagePath']),

		title() {
			return this.page.title === INDEX_PAGE
				? t('collectives', 'Landing page')
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
	height: 144px;
	width: 144px;
	margin-right: 12px;

	scroll-snap-align: start;
	background-color: var(--color-primary-element-light);
	border-radius: var(--border-radius-large);

	&:hover {
		background-color: var(--color-background-hover);
	}

	&__icon {
		display: flex;
		height: 72px;
		width: 144px;
		align-items: center;
		justify-content: center;
	}

	&__emoji {
		font-size: 32px;
	}

	&__text {
		padding: 12px;
	}

	&__title {
		margin-top: 12px;
		font-weight: bold;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__subtitle {
		display: flex;
		gap: 4px;
		margin-top: 8px;
		align-items: center;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;

		.timestamp {
			color: var(--color-text-maxcontrast);
		}
	}
}
</style>
