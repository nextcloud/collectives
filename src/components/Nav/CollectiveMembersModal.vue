<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('collectives', 'Members of collective {name}', { name: collective.name }, { escape: false })"
		size="normal"
		@closing="onClose">
		<div class="modal-collective-members">
			<a
				v-if="showTeamLink"
				class="team-link"
				:href="teamUrl"
				:title="t('collectives', 'Team overview')"
				target="_blank">
				<OpenInNewIcon :size="20" />
			</a>
			<MemberPicker
				:show-current="true"
				:circle-id="collective.circleId"
				:current-user-is-admin="currentUserIsAdmin"
				:current-members="circleMembersSorted(collective.circleId)"
				:on-click-searched="onClickSearched" />
		</div>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { mapActions, mapState } from 'pinia'
import NcDialog from '@nextcloud/vue/components/NcDialog'
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
		NcDialog,
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
:deep(.modal-container__content) {
	position: relative;
}

.modal-collective-members {
	height: 550px;
	max-height: 80vh;
	padding-bottom: 12px;
}

.team-link {
	position: absolute;
	top: 0;
	right: 12px;
	display: flex;
	align-items: center;
	height: var(--default-clickable-area);
	color: var(--color-text-maxcontrast);
	text-decoration: none;

	&:hover,
	&:focus-visible {
		color: var(--color-primary-element);
		transition: color var(--animation-quick) ease;
	}

	&:focus-visible {
		outline: 2px solid var(--color-main-text);
		border-radius: var(--border-radius-small);
	}
}
</style>
