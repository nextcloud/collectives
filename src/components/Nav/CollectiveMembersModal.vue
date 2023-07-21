<template>
	<NcModal :title="t('collectives', 'Members of collective {name}', { name: collective.name })" @close="onClose">
		<div class="modal-content">
			<div class="modal-collective-wrapper">
				<h2 class="modal-collective-title">
					{{ t('collectives', 'Members of collective {name}', { name: collective.name }) }}
				</h2>

				<div class="modal-collective-members">
					<MemberPicker :show-current="true"
						:current-members="circleMembers(collective.circleId)"
						@click-member="onClickMember" />
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { GET_CIRCLE_MEMBERS, ADD_MEMBERS_TO_CIRCLE } from '../../store/actions.js'
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

	data() {
		return {
			loading: false,
		}
	},

	computed: {
		...mapGetters([
			'circleMembers',
		]),
	},

	beforeMount() {
		this.dispatchGetCircleMembers(this.collective.circleId)
	},

	methods: {
		...mapMutations([
			'setMembersCollectiveId',
		]),

		...mapActions({
			dispatchGetCircleMembers: GET_CIRCLE_MEMBERS,
			dispatchAddMembersToCircle: ADD_MEMBERS_TO_CIRCLE,
		}),

		onClose() {
			this.$emit('close')
		},

		onClickMember(member) {
			console.debug('CollectiveMembersModal onClickMember', member)
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
}

.modal-collective-members {
	// Full height minus search field
	// Required for sticky search field
	height: calc(100% - 30px);
}
</style>
