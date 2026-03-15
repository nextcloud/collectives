<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		size="normal"
		class="collective-members-modal"
		@close="onClose">
		<div class="modal-collective-members">
			<h2 class="modal-collective-members__name">
				<template>
					{{ t('collectives', 'Members of collective {name}', { name: collective.name }, { escape: false }) }}
				</template>
				<a
					v-if="showTeamLink"
					class="team-link"
					:href="teamUrl"
					:title="t('collectives', 'Go to team overview')"
					target="_blank">
					<OpenInNewIcon :size="20" />
				</a>
			</h2>
			<MemberPicker
				:show-current="true"
				:circle-id="collective.circleId"
				:current-user-is-admin="currentUserIsAdmin"
				:current-members="circleMembersSorted(collective.circleId)"
				:on-click-searched="onClickSearched" />
		</div>
	</NcModal>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { mapActions, mapState } from 'pinia'
import NcModal from '@nextcloud/vue/components/NcModal'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import MemberPicker from '../Member/MemberPicker.vue'
import { autocompleteSourcesToCircleMemberTypes, circlesMemberTypes } from '../../constants.js'
import { useCirclesStore } from '../../stores/circles.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'CollectiveMembersModal',

	components: {
		MemberPicker,
		NcModal,
		OpenInNewIcon,
	},

	props: {
		collective: {
			required: true,
			type: Object,
		},
	},

	computed: {
		...mapState(useCirclesStore, ['circleMembersSorted']),
		...mapState(useCollectivesStore, ['isCollectiveAdmin']),
		...mapState(useRootStore, ['isPublic']),

		currentUserIsAdmin() {
			return this.isCollectiveAdmin(this.collective)
		},

		teamUrl() {
			return generateUrl('/apps/contacts/circle/{teamId}', { teamId: this.collective.circleId })
		},

		hasContactsApp() {
			return 'contacts' in window.OC.appswebroots
		},

		showTeamLink() {
			return this.hasContactsApp && !this.isPublic
		},
	},

	beforeMount() {
		this.getCircleMembers(this.collective.circleId)
	},

	methods: {
		t,

		...mapActions(useCirclesStore, ['getCircleMembers', 'addMemberToCircle']),

		onClose() {
			this.$emit('close')
		},

		async onClickSearched(member) {
			if (!this.currentUserIsAdmin) {
				return
			}

			await this.addMemberToCircle({
				circleId: this.collective.circleId,
				userId: member.id,
				type: circlesMemberTypes[autocompleteSourcesToCircleMemberTypes[member.source]],
			})
			await this.getCircleMembers(this.collective.circleId)
		},
	},
}
</script>

<style lang="scss" scoped>
.collective-members-modal {
	:deep(.modal-wrapper .modal-container) {
		display: flex !important;
		padding-block: 4px 0;
		padding-inline: 12px;
	}

	:deep(.modal-wrapper .modal-container__content) {
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
}

.modal-collective-members {
	height: 550px;
	max-height: 80vh;

	// Rules are mostly copied from NcDialog:
	&__name {
		font-size: 21px;
		text-align: center;
		line-height: var(--default-clickable-area);
		min-height: var(--default-clickable-area);
		overflow-wrap: break-word;
		margin-block: 0 12px;
		padding-inline: var(--default-clickable-area);

		&:hover .team-link,
		&:hover .material-design-icon {
			color: var(--color-primary-element);
			transition: color var(--animation-quick) ease;
		}
	}
}

.team-link {
	color: var(--color-main-text);

	.material-design-icon {
		display: inline;
		vertical-align: middle;
		color: var(--color-text-maxcontrast);
	}

	&:focus-visible {
		outline: 2px solid var(--color-main-text);
		border-radius: var(--border-radius-small);
	}
}
</style>
