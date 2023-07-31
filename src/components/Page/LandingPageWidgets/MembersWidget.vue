<template>
	<div class="members-widget">
		<WidgetHeading :title="t('collectives', 'Collective members')" />
		<div class="members-widget-members">
			<NcAvatar v-for="member in trimmedMembers"
				:key="member.singleId"
				:user="member.userId"
				:display-name="member.displayName"
				:is-no-user="isNoUser(member)"
				:icon-class="iconClass(member)"
				:disable-menu="true"
				:tooltip-message="member.displayName"
				:size="44" />
			<NcButton v-if="showMoreButton"
				type="secondary"
				:aria-label="t('collectives', 'Show all members of the collective')"
				@click="openCollectiveMembers()">
				<template #icon>
					<DotsHorizontalIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { NcAvatar, NcButton } from '@nextcloud/vue'
import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import { GET_CIRCLE_MEMBERS } from '../../../store/actions.js'
import { circlesMemberTypes } from '../../../constants.js'
import WidgetHeading from './WidgetHeading.vue'

const SHOW_MEMBERS_COUNT = 3

export default {
	name: 'MembersWidget',

	components: {
		DotsHorizontalIcon,
		NcAvatar,
		NcButton,
		WidgetHeading,
	},

	computed: {
		...mapGetters([
			'circleMembersSorted',
			'circleMemberType',
			'currentCollective',
			'isCollectiveAdmin',
			'recentPagesUserIds',
		]),

		sortedMembers() {
			return this.circleMembersSorted(this.currentCollective.circleId)
				.slice()
				.sort(this.sortLastActiveFirst)
		},

		trimmedMembers() {
			return this.sortedMembers
				.slice(0, SHOW_MEMBERS_COUNT)
		},

		isNoUser() {
			return function(member) {
				return this.circleMemberType(member) !== circlesMemberTypes.TYPE_USER
			}
		},

		iconClass() {
			return function(member) {
				return this.isNoUser(member) ? 'icon-group-white' : null
			}
		},

		showMoreButton() {
			return this.isCollectiveAdmin(this.currentCollective)
				|| this.sortedMembers.length > SHOW_MEMBERS_COUNT
		},
	},

	beforeMount() {
		this.dispatchGetCircleMembers(this.currentCollective.circleId)
	},

	methods: {
		...mapActions({
			dispatchGetCircleMembers: GET_CIRCLE_MEMBERS,
		}),

		...mapMutations([
			'setMembersCollectiveId',
		]),

		openCollectiveMembers() {
			this.setMembersCollectiveId(this.currentCollective.id)
		},

		/**
		 * @param {object} m1 First member
		 * @param {string} m1.userId First member user ID
		 * @param {object} m2 Second member
		 * @param {string} m2.userId Second member user ID
		 */
		sortLastActiveFirst(m1, m2) {
			if (this.recentPagesUserIds.includes(m1.userId) && this.recentPagesUserIds.includes(m2.userId)) {
				return this.recentPagesUserIds.indexOf(m1.userId) > this.recentPagesUserIds.indexOf(m2.userId)
			} else if (this.recentPagesUserIds.includes(m1.userId)) {
				return -1
			} else if (this.recentPagesUserIds.includes(m2.userId)) {
				return 1
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.members-widget-members {
	display: flex;
	flex-direction: row;
	gap: 12px;
	margin-top: 12px;
}
</style>
