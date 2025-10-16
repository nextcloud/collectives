<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sharing-list">
		<div class="sharing-list__content">
			<!-- collective members section -->
			<div class="sharing-list-item">
				<div class="sharing-list-item__header">
					<h4>{{ t('collectives', 'Collective members') }}</h4>
					<NcPopover popup-role="dialog" no-focus-trap>
						<template #trigger>
							<NcButton
								class="hint-icon"
								variant="tertiary-no-background"
								:aria-label="t('files_sharing', 'Member management explanation')">
								<template #icon>
									<InformationIcon :size="20" />
								</template>
							</NcButton>
						</template>
						<p class="hint-body">
							{{ membersManagementHelpText }}
						</p>
					</NcPopover>
				</div>
				<NcButton
					:disabled="!networkOnline"
					@click="openCollectiveMembers">
					{{ t('collectives', 'Manage collective members') }}
				</NcButton>
			</div>

			<!-- external shares section with loading state -->
			<div class="sharing-list-item">
				<div class="sharing-list-item__header sharing-list-item__header--external">
					<h4>{{ t('collectives', 'External shares') }}</h4>
					<NcPopover popup-role="dialog" no-focus-trap>
						<template #trigger>
							<NcButton
								class="hint-icon"
								variant="tertiary-no-background"
								:aria-label="t('files_sharing', 'External shares explanation')">
								<template #icon>
									<InformationIcon :size="20" />
								</template>
							</NcButton>
						</template>
						<p class="hint-body">
							{{ externalSharesHelpText }}
						</p>
					</NcPopover>
				</div>

				<!-- loading -->
				<NcEmptyContent v-if="loading('shares')">
					<template #icon>
						<NcLoadingIcon />
					</template>
				</NcEmptyContent>

				<!-- external shares list -->
				<ul v-else class="external-shares-list">
					<SharingEntryLink v-if="!shares.length" />
					<SharingEntryLink
						v-for="(share, index) in shares"
						v-else
						:key="share.id"
						:index="index + 1"
						:share="share" />
				</ul>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon, NcPopover } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import InformationIcon from 'vue-material-design-icons/InformationOutline.vue'
import SharingEntryLink from './SharingEntryLink.vue'
import { useNetworkState } from '../../composables/useNetworkState.ts'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { useSharesStore } from '../../stores/shares.js'

export default {
	name: 'SidebarTabSharing',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcPopover,
		SharingEntryLink,
		InformationIcon,
	},

	props: {
		pageId: {
			type: Number,
			required: true,
		},
	},

	setup() {
		const { networkOnline } = useNetworkState()
		return { networkOnline }
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useSharesStore, ['sharesByPageId']),
		...mapState(usePagesStore, ['isLandingPage']),
		...mapState(useCollectivesStore, ['currentCollective']),

		shares() {
			return this.isLandingPage
				? this.sharesByPageId(0)
				: this.sharesByPageId(this.pageId)
		},

		membersManagementHelpText() {
			return t('collectives', 'Manage members of your collective. Members have access to the whole collective.')
		},

		externalSharesHelpText() {
			return this.isLandingPage
				? t('collectives', 'Share this collective with others outside your collective via public links. Anyone with the link can access the shared content.')
				: t('collectives', 'Share this page with others outside your collective via public links. Anyone with the link can access the shared content.')
		},
	},

	methods: {
		...mapActions(useCollectivesStore, ['setMembersCollectiveId']),

		openCollectiveMembers() {
			this.setMembersCollectiveId(this.currentCollective.id)
		},
	},
}
</script>

<style scoped lang="scss">
.sharing-list {
	position: relative;
	height: 100%;

	&__content {
		padding: 0 6px;

		.sharing-list-item {
			padding-bottom: 16px;

			&__header {
				margin-bottom: 2px;
				display: flex;
				align-items: center;
				padding-bottom: 4px;

				h4 {
					margin: 0;
					font-size: 16px;
				}

				.hint-icon {
					color: var(--color-primary-element);
				}

				&--external {
					padding-top: 10px;
				}
			}
		}

		& > .sharing-list-item:not(:last-child) {
			border-bottom: 2px solid var(--color-border);
		}
	}
}

.hint-body {
	max-width: 300px;
	padding: var(--border-radius-element);
}
</style>
