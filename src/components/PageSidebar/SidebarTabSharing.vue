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
					<NcPopover popupRole="dialog" noFocusTrap>
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
					<NcPopover popupRole="dialog" noFocusTrap>
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

				<NcNoteCard
					v-if="showEditRestrictedNote"
					type="warning"
					:text="editRestrictedNoteText" />

				<!-- loading -->
				<NcEmptyContent v-if="loading('shares') && !sharesLoaded">
					<template #icon>
						<NcLoadingIcon />
					</template>
				</NcEmptyContent>

				<!-- external shares list -->
				<ul v-else class="external-shares-list">
					<SharingEntryLink v-if="!ownShares.length" />
					<SharingEntryLink
						v-for="(share, index) in ownShares"
						v-else
						:key="share.id"
						:index="index + 1"
						:share
						isOwnShare />
					<SharingEntryLink
						v-for="share in otherShares"
						:key="share.id"
						:index="otherShareIndexes[share.id]"
						:share />
				</ul>
			</div>
		</div>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import InformationIcon from 'vue-material-design-icons/InformationOutline.vue'
import SharingEntryLink from './SharingEntryLink.vue'
import { useNetworkState } from '../../composables/useNetworkState.ts'
import { memberLevels } from '../../constants.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { useSharesStore } from '../../stores/shares.js'
import displayError from '../../util/displayError.js'
import { sharesCanBeEditable } from '../../util/sharePermissions.ts'

export default {
	name: 'SidebarTabSharing',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcNoteCard,
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
		...mapState(useRootStore, ['activeSidebarTab', 'loading']),
		...mapState(useSharesStore, ['sharesByPageId', 'sharesLoaded']),
		...mapState(usePagesStore, ['isLandingPage']),
		...mapState(useCollectivesStore, ['currentCollective']),

		// Shares are only needed in the sharing tab, so load them when it's active and online
		loadSharesCollectiveId() {
			return this.activeSidebarTab === 'sharing' && this.networkOnline
				? this.currentCollective?.id
				: null
		},

		shares() {
			return this.isLandingPage
				? this.sharesByPageId(0)
				: this.sharesByPageId(this.pageId)
		},

		ownShares() {
			return this.shares.filter((share) => this.isOwnShare(share))
		},

		otherShares() {
			return this.shares.filter((share) => !this.isOwnShare(share))
		},

		otherShareIndexes() {
			const counts = {}
			return Object.fromEntries(this.otherShares.map((share) => {
				counts[share.owner] = (counts[share.owner] ?? 0) + 1
				return [share.id, counts[share.owner]]
			}))
		},

		membersManagementHelpText() {
			return t('collectives', 'Manage members of your collective. Members have access to the whole collective.')
		},

		externalSharesHelpText() {
			return this.isLandingPage
				? t('collectives', 'Share this collective with others outside your collective via public links. Anyone with the link can access the shared content.')
				: t('collectives', 'Share this page with others outside your collective via public links. Anyone with the link can access the shared content.')
		},

		showEditRestrictedNote() {
			return this.currentCollective.canEdit
				&& this.currentCollective.canShare
				&& !sharesCanBeEditable(this.currentCollective)
		},

		editRestrictedNoteText() {
			return this.currentCollective.editPermissionLevel >= memberLevels.LEVEL_ADMIN
				? t('collectives', 'Editing this collective is limited to admins. Share links are view only.')
				: t('collectives', 'Editing this collective is limited to admins and moderators. Share links are view only.')
		},
	},

	watch: {
		loadSharesCollectiveId: {
			handler(collectiveId) {
				if (collectiveId) {
					this.loadShares()
				}
			},

			immediate: true,
		},
	},

	methods: {
		t,

		...mapActions(useCollectivesStore, ['setMembersCollectiveId']),
		...mapActions(useSharesStore, ['getShares']),

		async loadShares() {
			try {
				await this.getShares()
			} catch (e) {
				displayError('Could not fetch shares')(e)
			}
		},

		isOwnShare(share) {
			return share.owner === getCurrentUser()?.uid
		},

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
