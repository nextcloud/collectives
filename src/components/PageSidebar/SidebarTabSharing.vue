<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sharing-list">
		<!-- loading -->
		<NcEmptyContent v-if="loading('shares')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- shares list -->
		<ul v-else class="sharing-list__content">
			<section>
				<div class="section-header">
					<h4>{{ t('collectives', 'Collective members') }}</h4>
					<NcPopover popup-role="dialog">
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
					@click="openCollectiveMembers">
					{{ t('collectives', 'Manage collective members') }}
				</NcButton>
			</section>
			<section>
				<div class="section-header">
					<h4>External shares</h4>
					<NcPopover popup-role="dialog">
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
				<SharingEntryLink v-if="!shares.length" />
				<SharingEntryLink
					v-for="(share, index) in shares"
					v-else
					:key="share.id"
					:index="index + 1"
					:share="share" />
			</section>
		</ul>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon, NcPopover } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import InformationIcon from 'vue-material-design-icons/InformationOutline.vue'
import SharingEntryLink from './SharingEntryLink.vue'
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

	data() {
		return {
			membersManagementHelpText: t('collectives', 'Share this page with members of your collective. Members can view and edit the shared content based on their permissions.'),
			externalSharesHelpText: t('collectives', 'Share this page or collective with others outside your collective via public links. Anyone with the link can access the shared content.'),
		}
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

		section {
			padding-bottom: 16px;

			.section-header {
				margin-top: 2px;
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

			}

		}

		& > section:not(:last-child) {
			border-bottom: 2px solid var(--color-border);
		}

	}
}

.hint-body {
	max-width: 300px;
	padding: var(--border-radius-element);
}
</style>
