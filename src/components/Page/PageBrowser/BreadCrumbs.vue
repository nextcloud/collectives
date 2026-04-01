<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="crumbs">
		<div v-if="!selectedCollective || !selectedCollective.isPageShare" class="crumbs-home">
			<NcButton
				variant="tertiary"
				:aria-label="t('collectives', 'Breadcrumb for list of collectives')"
				:disabled="!selectedCollective"
				class="crumb-button home"
				@click="$emit('clickCollectivesList')">
				<template #icon>
					<CollectivesIcon :size="20" />
				</template>
				{{ collectivesCrumbString }}
			</NcButton>
			<ChevronRightIcon :size="20" />
		</div>
		<template v-if="selectedCollective">
			<div class="crumbs-level">
				<NcButton
					variant="tertiary"
					:aria-label="collectiveBreadcrumbAriaLabel"
					:disabled="pageCrumbs.length === 0"
					class="crumb-button"
					@click="$emit('clickCollectiveHome')">
					<template v-if="collectiveBreadcrumbEmoji" #icon>
						{{ collectiveBreadcrumbEmoji }}
					</template>
					{{ collectiveBreadcrumbTitle }}
				</NcButton>
			</div>
			<div
				v-for="(page, index) in pageCrumbs"
				:key="page.id"
				class="crumbs-level">
				<ChevronRightIcon :size="20" />
				<NcButton
					variant="tertiary"
					:aria-label="t('collectives', 'Breadcrumb, navigate to page {page}', { page: page.title })"
					:disabled="(index + 1) === pageCrumbs.length"
					class="crumb-button"
					@click="$emit('clickPage', page)">
					{{ page.title }}
				</NcButton>
			</div>
		</template>
	</div>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { PageInfo } from '../../../types.ts'

import { t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import CollectivesIcon from '../../Icon/CollectivesIcon.vue'

export default defineComponent({
	name: 'BreadCrumbs',

	components: {
		CollectivesIcon,
		ChevronRightIcon,
		NcButton,
	},

	props: {
		selectedCollective: {
			type: Object,
			required: true,
		},

		pageCrumbs: {
			type: Array as PropType<PageInfo[]>,
			required: true,
		},
	},

	emits: [
		'clickCollectivesList',
		'clickCollectiveHome',
		'clickPage',
	],

	computed: {
		collectivesCrumbString() {
			return this.selectedCollective
				? ''
				: t('collectives', 'All collectives')
		},

		collectiveBreadcrumbAriaLabel() {
			return this.selectedCollective.isPageShare
				? t('collectives', 'Breadcrumb for page {name}', { name: this.rootPage.title })
				: t('collectives', 'Breadcrumb for collective {name}', { name: this.selectedCollective.name })
		},

		collectiveBreadcrumbEmoji() {
			return this.selectedCollective.isPageShare
				? this.rootPage.emoji
				: this.selectedCollective.emoji
		},

		collectiveBreadcrumbTitle() {
			return this.selectedCollective.isPageShare
				? this.rootPage.title
				: this.selectedCollective.name
		},
	},

	methods: {
		t,
	},
})
</script>

<style scoped lang="scss">
.crumbs {
	color: var(--color-text-maxcontrast);
	display: inline-flex;
	padding-right: 0;
	padding-bottom: 8px;

	div {
		display: flex;
		text-overflow: ellipsis;
		white-space: nowrap;
		overflow: hidden;
		max-width: 300px;

		.crumb-button {
			color: var(--color-text-maxcontrast);

			&.home {
				padding-left: 0;
				// Remove padding, add margin to not make the button bigger
				padding-right: 0;
				margin-right: var(--button-padding);
				font-weight: bold;
			}
		}

		&.crumbs-home {
			flex-shrink: 0;
		}

		&.crumbs-level {
			display: inline-flex;
			min-width: 65px;

			&:last-child {
				flex-shrink: 0;
			}
		}

		&:last-child {
			.crumb-button {
				color: var(--color-main-text);
			}
		}
	}
}
</style>
