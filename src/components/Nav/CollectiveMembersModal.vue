<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal @close="onClose">
		<div class="modal-content">
			<div class="modal-collective-wrapper">
				<h2 class="modal-collective-title">
					{{ t('collectives', 'Members of collective {name}', { name: collective.name }) }}
				</h2>

				<div class="modal-collective-members">
					<MemberPicker :show-current="true"
						:circle-id="collective.circleId"
						:current-user-is-admin="currentUserIsAdmin"
						:current-members="circleMembersSorted(collective.circleId)"
						:on-click-searched="onClickSearched" />
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import { useCirclesStore } from '../../stores/circles.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { autocompleteSourcesToCircleMemberTypes, circlesMemberTypes } from '../../constants.js'
import { NcModal } from '@nextcloud/vue'
import MemberPicker from '../Member/MemberPicker.vue'

export default {
	name: 'CollectiveMembersModal',

	components: {
		MemberPicker,
		NcModal,
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

		currentUserIsAdmin() {
			return this.isCollectiveAdmin(this.collective)
		},
	},

	beforeMount() {
		this.getCircleMembers(this.collective.circleId)
	},

	methods: {
		...mapActions(useCirclesStore, ['getCircleMembers', 'addMemberToCircle']),
		...mapActions(useCollectivesStore, ['setMembersCollectiveId']),

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
.modal-content {
	display: flex;
	flex-direction: column;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 16px;
	padding-bottom: 18px;
}

.modal-collective-wrapper {
	display: flex;
	flex-direction: column;
	width: 100%;
	height: 550px;
	max-height: 80vh;
}

.modal-collective-members {
	// Full height minus search field
	// Required for sticky search field
	height: calc(100% - 30px);
}
</style>
