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
			<MemberPicker
				:showCurrent="true"
				:circleId="collective.circleId"
				:currentUserIsAdmin="currentUserIsAdmin"
				:currentMembers="circleMembersSorted(collective.circleId)"
				:onClickSearched="onClickSearched" />
		</div>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import MemberPicker from '../Member/MemberPicker.vue'
import { autocompleteSourcesToCircleMemberTypes, circlesMemberTypes } from '../../constants.js'
import { useCirclesStore } from '../../stores/circles.js'
import { useCollectivesStore } from '../../stores/collectives.js'

export default {
	name: 'CollectiveMembersModal',

	components: {
		MemberPicker,
		NcDialog,
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
.modal-collective-members {
	height: 550px;
	max-height: 80vh;
	padding-bottom: 12px;
}
</style>
