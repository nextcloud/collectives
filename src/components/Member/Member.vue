<template>
	<li class="member-row"
		:tabindex="tabindex"
		:role="ariaRole"
		:class="{
			'clickable': isClickable,
			'selected': isSelected,
		}"
		@click="onClick"
		@keyup.enter="onClick">
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

		<!-- Loading icon -->
		<div v-if="isLoading || isLoadingLevel" class="member-row__loading">
			<NcLoadingIcon :size="20" />
		</div>

		<!-- Checkmark icon for selected -->
		<div v-if="currentUserIsAdmin && isSearched && isSelected" class="member-row__checkmark">
			<CheckIcon :size="20" />
		</div>

		<!-- Action menu -->
		<NcActions v-else-if="currentUserIsAdmin && !isSearched && !isCurrentUser"
			:force-menu="true"
			class="member-row__actions">
			<NcActionButton v-if="!isAdmin"
				:close-after-click="true"
				@click="setMemberLevel(memberLevels.LEVEL_ADMIN)">
				<template #icon>
					<CrownIcon :size="20" />
				</template>
				{{ t('collectives', 'Promote to admin') }}
			</NcActionButton>
			<NcActionButton v-if="!isModerator"
				:close-after-click="true"
				@click="setMemberLevel(memberLevels.LEVEL_MODERATOR)">
				<template #icon>
					<AccountCogIcon :size="20" />
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
	</li>
</template>

<script>
import { mapActions } from 'vuex'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { circlesMemberTypes, memberLevels } from '../../constants.js'
import {
	GET_CIRCLE_MEMBERS,
	CHANGE_CIRCLE_MEMBER_LEVEL,
	REMOVE_MEMBER_FROM_CIRCLE,
} from '../../store/actions.js'
import { NcActions, NcActionButton, NcActionSeparator, NcAvatar, NcLoadingIcon } from '@nextcloud/vue'
import AccountCogIcon from 'vue-material-design-icons/AccountCog.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CrownIcon from 'vue-material-design-icons/Crown.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'Member',

	components: {
		AccountCogIcon,
		AccountIcon,
		CheckIcon,
		CrownIcon,
		DeleteIcon,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		NcAvatar,
		NcLoadingIcon,
	},

	props: {
		circleId: {
			type: String,
			default: null,
		},
		currentUserIsAdmin: {
			type: Boolean,
			default: true,
		},
		memberId: {
			type: String,
			default: null,
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
		isSelected: {
			type: Boolean,
			default: false,
		},
		isLoading: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			isLoadingLevel: false,
			memberLevels,
		}
	},

	computed: {
		isClickable() {
			return this.isSearched
		},
		tabindex() {
			return this.isClickable ? 0 : undefined
		},
		ariaRole() {
			return this.isClickable ? 'button' : undefined
		},
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
			if (this.circleId) {
				this.isLoadingLevel = true
				await this.dispatchChangeCircleMemberLevel({ circleId: this.circleId, memberId: this.memberId, level })
					.then(async () => {
						showSuccess(t('collectives', 'Member level changed'))
						await this.dispatchGetCircleMembers(this.circleId)
					}).catch((error) => {
						showError(t('collectives', 'Could not change member level'))
						throw error
					}).finally(() => {
						this.isLoadingLevel = false
					})
			}
		},

		async removeMember() {
			if (this.circleId) {
				this.isLoadingLevel = true
				await this.dispatchRemoveMemberFromCircle({ circleId: this.circleId, memberId: this.memberId })
					.then(async () => {
						showSuccess(t('collectives', 'Member removed'))
						await this.dispatchGetCircleMembers(this.circleId)
					}).catch((error) => {
						showError(t('collectives', 'Could not remove member'))
						throw error
					}).finally(() => {
						this.isLoadingLevel = false
					})
				this.isLoadingLevel = false
			}
		},

		onClick(event) {
			if (this.isClickable) {
				this.$emit('click')
			}
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

	&__loading {
		margin-right: 8px;
	}

	&:hover, &:focus {
		background-color: var(--color-background-hover);
	}

	&.clickable {
		cursor: pointer;

		.avatar-class-icon,
		.member-row__user-descriptor,
		.member-row__user-name,
		.member-row__level-indicator {
			cursor: pointer;
		}

		// If clickable, show primary bg when selected and on hovering
		&:hover, &:focus, &.selected {
			background-color: var(--color-primary-element-light);
		}
	}
}
</style>
