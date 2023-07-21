<template>
	<div>
		<div class="member-row" :class="{'is-searched': isSearched}">
			<!-- Avatar -->
			<NcAvatar :user="userId"
				:is-no-user="isNoUser"
				:icon-class="iconClass"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:size="44" />

			<!-- Data -->
			<div class="member-row__user-descriptor">
				<span class="member-row__user-name">{{ displayName }}</span>
				<span v-if="showLevelLabel" class="member-row__level-indicator">({{ levelLabel }})</span>
			</div>

			<!-- Action menu -->
			<NcActions v-if="!isCurrentUser && !isSearched"
				:force-menu="true"
				class="member-row__actions">
				<NcActionButton v-if="!isAdmin"
					:close-after-click="true"
					@click="setMemberLevel(memberLevels.LEVEL_ADMIN)">
					<template #icon>
						<AccountCogIcon :size="20" />
					</template>
					{{ t('collectives', 'Promote to admin') }}
				</NcActionButton>
				<NcActionButton v-if="!isModerator"
					:close-after-click="true"
					@click="setMemberLevel(memberLevels.LEVEL_MODERATOR)">
					<template #icon>
						<CrownIcon :size="20" />
					</template>
					{{ setLevelModeratorString }}
				</NcActionButton>
				<NcActionButton v-if="!isMember"
					:close-after-click="true"
					@click="setMemberLevel(memberLevels.LEVEL_MEMBER)">
					<template #icon>
						<AccountIcon :size="20" />
					</template>
					{{ t('collectives', 'Demote to member') }}
				</NcActionButton>
				<NcActionSeparator />
				<NcActionButton :close-after-click="true"
					@click="removeMember">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('collectives', 'Remove') }}
				</NcActionButton>
			</NcActions>
		</div>
	</div>
</template>

<script>
import { mapActions } from 'vuex'
import { circlesMemberTypes, memberLevels } from '../../constants.js'
import {
	GET_CIRCLE_MEMBERS,
	CHANGE_CIRCLE_MEMBER_LEVEL,
	REMOVE_MEMBER_FROM_CIRCLE,
} from '../../store/actions.js'
import { NcActions, NcActionButton, NcActionSeparator, NcAvatar } from '@nextcloud/vue'
import AccountCogIcon from 'vue-material-design-icons/AccountCog.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import CrownIcon from 'vue-material-design-icons/Crown.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'Member',

	components: {
		AccountCogIcon,
		AccountIcon,
		CrownIcon,
		DeleteIcon,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		NcAvatar,
	},

	props: {
		circleId: {
			type: String,
			required: true,
		},
		memberId: {
			type: String,
			required: true,
		},
		userId: {
			type: String,
			required: true,
		},
		displayName: {
			type: String,
			required: true,
		},
		userType: {
			type: Number,
			required: true,
		},
		level: {
			type: Number,
			default: memberLevels.LEVEL_MEMBER,
		},
		isCurrentUser: {
			type: Boolean,
			default: false,
		},
		isSearched: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			memberLevels,
		}
	},

	computed: {
		isNoUser() {
			return this.userType !== circlesMemberTypes.TYPE_USER
		},
		iconClass() {
			return this.isNoUser ? 'icon-group-white' : null
		},
		isAdmin() {
			return this.level >= memberLevels.LEVEL_ADMIN
		},
		isModerator() {
			return this.level === memberLevels.LEVEL_MODERATOR
		},
		isMember() {
			return this.level === memberLevels.LEVEL_MEMBER
		},
		levelLabel() {
			return this.isAdmin
				? t('collectives', 'admin')
				: this.isModerator
					? t('collectives', 'moderator')
					: t('collectives', 'member')
		},
		showLevelLabel() {
			return this.isAdmin || this.isModerator
		},
		setLevelModeratorString() {
			return this.isAdmin
				? t('collectives', 'Demote to moderator')
				: t('collectives', 'Promote to moderator')
		},
	},

	methods: {
		...mapActions({
			dispatchGetCircleMembers: GET_CIRCLE_MEMBERS,
			dispatchChangeCircleMemberLevel: CHANGE_CIRCLE_MEMBER_LEVEL,
			dispatchRemoveMemberFromCircle: REMOVE_MEMBER_FROM_CIRCLE,
		}),

		async setMemberLevel(level) {
			await this.dispatchChangeCircleMemberLevel({ circleId: this.circleId, memberId: this.memberId, level })
			await this.dispatchGetCircleMembers(this.circleId)
		},
		async removeMember() {
			await this.dispatchRemoveMemberFromCircle({ circleId: this.circleId, memberId: this.memberId })
			await this.dispatchGetCircleMembers(this.circleId)
		},
	},
}
</script>

<style scoped lang="scss">
.member-row {
	display: flex;
	align-items: center;
	margin: 4px 0;
	border-radius: var(--border-radius-pill);
	height: 56px;
	padding: 0 4px;

	&__user-descriptor {
		// margin-top: -4px;
		margin-left: 12px;
		width: calc(100% - 100px);

		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__user-name,
	&__level-indicator {
		vertical-align: middle;
		line-height: normal;
	}

	&__level-indicator {
		color: var(--color-text-maxcontrast);
		font-weight: 300;
		padding-left: 5px;
	}

	&:hover, &:focus {
		background-color: var(--color-background-hover);
	}

	.is-searched {
		// Show primary bg on hovering entities
		&:hover, &:focus {
			background-color: var(--color-primary-element-light);
		}
	}
}

.member-picker {
	&-caption:not(:first-child) {
		margin-top: 0;
	}

	&-bubble {
		// Overwrite .user-bubble__wrapper styling from NcUserBubble
		display: flex !important;
		margin-bottom: 4px;

		:deep(.user-bubble__content) {
			background-color: var(--color-main-background);
			align-items: center;
			width: 100%;
		}

		:deep(.user-bubble__title) {
			width: calc(100% - 80px);
		}

		// Show primary bg on hovering entities
		&:hover, &:focus {
			:deep(.user-bubble__content) {
				// better visual with light default tint
				background-color: var(--color-primary-element-light);
			}
		}
	}
}
</style>
