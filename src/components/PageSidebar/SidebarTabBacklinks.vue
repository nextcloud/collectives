<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="backlinks-container">
		<!-- backlinks list -->
		<div v-if="currentBacklinks.length">
			<ul class="backlink-list">
				<NcListItem
					v-for="backlinkPage in currentBacklinks"
					:key="backlinkPage.id"
					:name="pagePathTitle(backlinkPage)"
					:to="pagePath(backlinkPage)"
					class="backlink">
					<template #icon>
						<div
							v-if="backlinkPage.emoji"
							class="item-icon item-icon__emoji">
							{{ backlinkPage.emoji }}
						</div>
						<PageIcon
							v-else
							:size="26"
							fill-color="var(--color-main-background)"
							class="item-icon item-icon__page" />
					</template>
					<template #subname>
						{{ lastUpdate(page) }}
					</template>
				</NcListItem>
			</ul>
		</div>

		<!-- no backlinks found -->
		<NcEmptyContent
			v-else
			:name="t('collectives', 'No backlinks available')"
			:description="t('collectives', 'If other pages link to this one, they will be listed here.')">
			<template #icon>
				<ArrowBottomLeftIcon />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import moment from '@nextcloud/moment'
import { NcEmptyContent, NcListItem } from '@nextcloud/vue'
import { mapState } from 'pinia'
import ArrowBottomLeftIcon from 'vue-material-design-icons/ArrowBottomLeft.vue'
import PageIcon from '../Icon/PageIcon.vue'
import { usePagesStore } from '../../stores/pages.js'

export default {
	name: 'SidebarTabBacklinks',

	components: {
		NcEmptyContent,
		NcListItem,
		ArrowBottomLeftIcon,
		PageIcon,
	},

	props: {
		page: {
			type: Object,
			required: true,
		},
	},

	computed: {
		...mapState(usePagesStore, [
			'backlinks',
			'pagePath',
			'pagePathTitle',
		]),

		currentBacklinks() {
			return this.backlinks(this.page.id)
		},

		lastUpdate() {
			return (page) => moment.unix(page.timestamp).fromNow()
		},
	},
}
</script>

<style lang="scss" scoped>
.backlink {
	display: flex;
	flex-direction: row;

	:deep(.line-one__name) {
		font-weight: normal;
	}

	.item-icon {
		height: 34px;
		border-radius: var(--border-radius);

		&__emoji {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 26px;
			font-size: 1.3em;
		}

		&__page {
			background-color: var(--color-background-darker);
		}
	}
}
</style>
