<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li
		class="member-row"
		:tabindex="tabindex"
		:role="ariaRole"
		:class="{
			clickable: isClickable,
			selected: isSelected,
		}"
		@click="onClick"
		@keyup.enter="onClick">
		<!-- Avatar -->
		<NcAvatar
			:user="userId"
			:is-no-user="isNoUser"
			:icon-class="iconClass"
			:disable-menu="true"
			:disable-tooltip="true"
			:hide-status="true"
			:size="avatarSize" />

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
		<NcActions
			v-else-if="currentUserIsAdmin && !isSearched && !isCurrentUser"
			:force-menu="true"
			:open.sync="showActionMenu"
			class="member-row__actions">
			<NcActionButton
				v-if="!isAdmin"
				:close-after-click="true"
				@click="setMemberLevel(memberLevels.LEVEL_ADMIN)">
				<template #icon>
					<CrownIcon :size="20" />
				</template>
				{{ t('collectives', 'Promote to admin') }}
			</NcActionButton>
			<NcActionButton
				v-if="!isModerator"
				:close-after-click="true"
				@click="setMemberLevel(memberLevels.LEVEL_MODERATOR)">
				<template #icon>
					<AccountCogIcon :size="20" />
				</template>
				{{ setLevelModeratorString }}
			</NcActionButton>
			<NcActionButton
				v-if="!isMember"
				:close-after-click="true"
				@click="setMemberLevel(memberLevels.LEVEL_MEMBER)">
				<template #icon>
					<AccountIcon :size="20" />
				</template>
				{{ t('collectives', 'Demote to member') }}
			</NcActionButton>
			<NcActionSeparator />
			<NcActionButton
				:close-after-click="true"
				class="critical"
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { NcActionButton, NcActions, NcActionSeparator, NcAvatar, NcLoadingIcon } from '@nextcloud/vue'
import { mapActions } from 'pinia'
import AccountCogIcon from 'vue-material-design-icons/AccountCogOutline.vue'
import AccountIcon from 'vue-material-design-icons/AccountOutline.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CrownIcon from 'vue-material-design-icons/CrownOutline.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import { circlesMemberTypes, memberLevels } from '../../constants.js'
import { useCirclesStore } from '../../stores/circles.js'

export default {
	name: 'MemberItem',

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
			required: true,
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
			required: true,
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
			showActionMenu: false,
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

		avatarSize() {
			return parseInt(window.getComputedStyle(document.body).getPropertyValue('--default-clickable-area'))
		},
	},

	mounted() {
		subscribe('collectives:member-picker:scroll', this.closeMenu)
	},

	unmounted() {
		unsubscribe('collectives:member-picker:scroll', this.closeMenu)
	},

	methods: {
		...mapActions(useCirclesStore, [
			'getCircleMembers',
			'changeCircleMemberLevel',
			'removeMemberFromCircle',
		]),

		async setMemberLevel(level) {
			if (this.circleId) {
				this.isLoadingLevel = true
				await this.changeCircleMemberLevel({ circleId: this.circleId, memberId: this.memberId, level })
					.then(async () => {
						showSuccess(t('collectives', 'Member level changed'))
						await this.getCircleMembers(this.circleId)
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
				await this.removeMemberFromCircle({ circleId: this.circleId, memberId: this.memberId })
					.then(async () => {
						showSuccess(t('collectives', 'Member removed'))
						await this.getCircleMembers(this.circleId)
					}).catch((error) => {
						showError(t('collectives', 'Could not remove member'))
						throw error
					}).finally(() => {
						this.isLoadingLevel = false
					})
				this.isLoadingLevel = false
			}
		},

		onClick() {
			if (this.isClickable) {
				this.$emit('click')
			}
		},

		closeMenu() {
			this.showActionMenu = false
		},
	},
}
</script>

<style scoped lang="scss">
.member-row {
	display: flex;
	align-items: center;
	margin: 4px 0;
	border-radius: var(--border-radius-element, var(--border-radius-large));
	height: calc(var(--default-clickable-area) + 12px);
	padding: 0 4px;

	&__user-descriptor {
		display: flex;
		flex-grow: 1;
		margin-left: 12px;

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
		padding-left: 5px;
	}

	&__loading {
		margin-right: 8px;
	}

	&__checkmark {
		padding-right: 8px;
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

.critical > :deep(.action-button) {
	color: var(--color-element-error, var(--color-error));
}
</style>
