<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<transition-group
		v-if="hasSelectedMembers"
		name="zoom"
		tag="div"
		class="selected-members">
		<NcUserBubble
			v-for="member in selectedMembers"
			:key="`member-${member.source}-${member.id}`"
			:margin="0"
			:size="22"
			:display-name="member.label"
			:avatar-image="selectedMemberAvatarImage(member)"
			:primary="isCurrentUser(member)"
			class="selected-member-bubble">
			<template v-if="selectedMemberDeletable(member)" #title>
				<a
					href="#"
					:title="t('collectives', 'Remove {name}', { name: member.label })"
					class="selected-member-bubble-delete"
					@click="deleteMember(member)">
					<CloseIcon :size="16" />
				</a>
			</template>
		</NcUserBubble>
	</transition-group>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { NcUserBubble } from '@nextcloud/vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

export default {
	name: 'SelectedMembers',

	components: {
		CloseIcon,
		NcUserBubble,
	},

	props: {
		selectedMembers: {
			type: Object,
			required: true,
		},

		noDeleteMembers: {
			type: Array,
			default() {
				return []
			},
		},
	},

	computed: {
		isCurrentUser() {
			return (member) => member.source === 'users' && member.label === getCurrentUser().uid
		},

		hasSelectedMembers() {
			return Object.keys(this.selectedMembers).length !== 0
		},

		selectedMemberAvatarImage() {
			return (member) => member.source === 'users' ? null : 'icon-group-white'
		},

		selectedMemberDeletable() {
			return (member) => !this.noDeleteMembers.includes(`${member.source}-${member.id}`)
		},
	},

	methods: {
		deleteMember(member) {
			this.$emit('delete-from-selection', member)
		},
	},
}

</script>

<style scoped lang="scss">
.selected-members {
	display: flex;
	flex-wrap: wrap;
	border-bottom: 1px solid var(--color-background-darker);
	padding: 4px 0;
	max-height: 97px;
	overflow-y: auto;
	flex: 1 0 auto;
	align-content: flex-start;

	.selected-member-bubble {
		max-width: calc(50% - 4px);
		margin-right: 4px;
		margin-bottom: 4px;

		:deep(.user-bubble__content) {
			align-items: center;
		}

		&-delete {
			display: block;
			margin-right: -4px;
			opacity: .7;

			&:hover, &active, &focus {
				opacity: 1;
			}
		}
	}
}

.zoom-enter-active {
	animation: zoom-in var(--animation-quick);
}

.zoom-leave-active {
	animation: zoom-in var(--animation-quick) reverse;
	will-change: transform;
}

@keyframes zoom-in {
	0% {
		transform: scale(0);
	}
	100% {
		transform: scale(1);
	}
}
</style>
