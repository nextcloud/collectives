<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="members-widget">
		<a class="members-title"
			:aria-label="expandLabel"
			@keydown.enter="toggleWidget"
			@click="toggleWidget">
			<WidgetHeading :title="t('collectives', 'Members')" />
			<div class="toggle-icon">
				<ChevronDownIcon :size="24"
					:class="{ 'collapsed': !showMembers }" />
			</div>
		</a>
		<div v-show="showMembers" class="members-container">
			<div class="members-avatars">
				<SkeletonLoading v-if="loading"
					type="avatar"
					:count="3"
					class="members-skeleton" />
				<div v-else ref="members" class="members-members">
					<NcAvatar v-for="member in trimmedMembers"
						:key="member.singleId"
						:user="member.userId"
						:display-name="member.displayName"
						:is-no-user="isNoUser(member)"
						:icon-class="iconClass(member)"
						:disable-menu="true"
						:tooltip-message="member.displayName"
						:size="avatarSize" />
					<NcButton type="secondary"
						:title="showMembersTitle"
						:aria-label="showMembersAriaLabel"
						@click="openCollectiveMembers()">
						<template #icon>
							<AccountMultiplePlusIcon v-if="isAdmin" :size="16" />
							<AccountMultipleIcon v-else :size="16" />
						</template>
					</NcButton>
				</div>
			</div>
			<NcButton v-if="showTeamOverviewButton" :href="teamUrl" target="_blank">
				<template #icon>
					<TeamsIcon :size="20" />
				</template>
				<template v-if="!isMobile" #default>
					{{ t('collectives','Team overview') }}
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import debounce from 'debounce'
import { mapActions, mapState } from 'pinia'
import { useCirclesStore } from '../../../stores/circles.js'
import { useCollectivesStore } from '../../../stores/collectives.js'
import { usePagesStore } from '../../../stores/pages.js'
import { generateUrl } from '@nextcloud/router'
import { circlesMemberTypes } from '../../../constants.js'
import { showError } from '@nextcloud/dialogs'

import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'

import { NcAvatar, NcButton } from '@nextcloud/vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import AccountMultiplePlusIcon from 'vue-material-design-icons/AccountMultiplePlus.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import SkeletonLoading from '../../SkeletonLoading.vue'
import TeamsIcon from '../../Icon/TeamsIcon.vue'
import WidgetHeading from './WidgetHeading.vue'

export default {
	name: 'MembersWidget',

	components: {
		AccountMultipleIcon,
		AccountMultiplePlusIcon,
		ChevronDownIcon,
		NcAvatar,
		NcButton,
		SkeletonLoading,
		TeamsIcon,
		WidgetHeading,
	},

	mixins: [
		isMobile,
	],

	data() {
		return {
			showMembersCount: 3,
			updateShowMembersCountDebounced: debounce(this.updateShowMembersCount, 50),
		}
	},

	computed: {
		...mapState(useCirclesStore, [
			'circleMembers',
			'circleMembersSorted',
			'circleMemberType',
		]),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'isCollectiveAdmin',
		]),
		...mapState(usePagesStore, ['recentPagesUserIds']),

		expandLabel() {
			return this.showMembers
				? t('collectives', 'Collapse members')
				: t('collectives', 'Expand members')
		},

		showMembers() {
			return this.currentCollective.userShowMembers ?? true
		},

		sortedMembers() {
			return this.circleMembersSorted(this.currentCollective.circleId)
				.slice()
				.sort(this.sortLastActiveFirst)
		},

		trimmedMembers() {
			return this.sortedMembers
				.slice(0, this.showMembersCount)
		},

		loading() {
			return this.trimmedMembers.length === 0
		},

		isNoUser() {
			return function(member) {
				return this.circleMemberType(member) !== circlesMemberTypes.TYPE_USER
			}
		},

		avatarSize() {
			return parseInt(window.getComputedStyle(document.body).getPropertyValue('--default-clickable-area'))
		},

		iconClass() {
			return function(member) {
				return this.isNoUser(member) ? 'icon-group-white' : null
			}
		},

		isAdmin() {
			return this.isCollectiveAdmin(this.currentCollective)
		},

		showMembersTitle() {
			return this.isAdmin
				? t('collectives', 'Manage members')
				: t('collectives', 'Show members')
		},

		showMembersAriaLabel() {
			return this.isAdmin
				? t('collectives', 'Manage members of the collective')
				: t('collectives', 'Show all members of the collective')
		},

		teamUrl() {
			return generateUrl('/apps/contacts/circle/{teamId}', { teamId: this.currentCollective.circleId })
		},

		hasContactsApp() {
			return 'contacts' in this.OC.appswebroots
		},

		showTeamOverviewButton() {
			return this.hasContactsApp && this.circleMembers(this.currentCollective.circleId).length > 1
		},
	},

	watch: {
		'sortedMembers.length'() {
			this.$nextTick(() => {
				this.updateShowMembersCountDebounced()
			})
		},
	},

	beforeMount() {
		this.getCircleMembers(this.currentCollective.circleId)
	},

	mounted() {
		window.addEventListener('resize', this.updateShowMembersCountDebounced)
	},

	unmounted() {
		window.removeEventListener('resize', this.updateShowMembersCountDebounced)
	},

	methods: {
		...mapActions(useCirclesStore, ['getCircleMembers']),
		...mapActions(useCollectivesStore, [
			'setCollectiveUserSettingShowMembers',
			'setMembersCollectiveId',
		]),

		updateShowMembersCount() {
			// How many avatars (default-clickable-area + 12px gap) fit? Subtract one for the more button.
			const membersWidth = this.$refs.members?.clientWidth
			const defaultClickableArea = parseInt(window.getComputedStyle(document.body).getPropertyValue('--default-clickable-area'))
			const avatarHeight = defaultClickableArea + 12
			if (membersWidth) {
				const maxMembers = Math.floor(membersWidth / avatarHeight) - 1
				this.showMembersCount = Math.min(this.sortedMembers.length, maxMembers)
			}
		},

		toggleWidget() {
			this.setCollectiveUserSettingShowMembers({ id: this.currentCollective.id, showMembers: !this.showMembers })
				.catch((error) => {
					console.error(error)
					showError(t('collectives', 'Could not save show members setting for collective'))
				})
		},

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
.members-title {
	display: flex;

	.toggle-icon {
		display: flex;
		padding: 24px 0 12px 8px;

		.collapsed {
			transition: transform var(--animation-slow);
			transform: rotate(-90deg);
		}
	}
}

.members-container {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.members-avatars {
	flex-grow: 1;
}

.members-skeleton {
	height: var(--default-clickable-area);
}

.members-members {
	display: flex;
	flex-direction: row;
	gap: 12px;
}
</style>
