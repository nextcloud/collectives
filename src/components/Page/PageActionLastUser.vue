<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li v-if="lastUserDisplayName" class="action action--user-bubble">
		<button class="action-button action-button--user-bubble" type="button">
			<ClockIcon :size="20" />
			<LastUserBubble
				:last-user-id="lastUserId"
				:last-user-display-name="lastUserDisplayName"
				:timestamp="timestamp" />
		</button>
	</li>
</template>

<script>
import ClockIcon from 'vue-material-design-icons/ClockOutline.vue'
import LastUserBubble from '../LastUserBubble.vue'

export default {
	name: 'PageActionLastUser',

	components: {
		ClockIcon,
		LastUserBubble,
	},

	props: {
		timestamp: {
			type: Number,
			required: true,
		},

		lastUserId: {
			type: String,
			default: null,
		},

		lastUserDisplayName: {
			type: String,
			default: null,
		},
	},
}
</script>

<style lang="scss" scoped>
.action--user-bubble {
	pointer-events: none;
}

.action-button--user-bubble {
	display: flex;
	align-items: flex-start;
	width: 100%;
	height: auto;
	margin: 0;
	padding: 0;
	padding-right: calc((var(--default-clickable-area) - 16px) / 2);
	box-sizing: border-box;
	white-space: nowrap;
	opacity: .7;
	border: 0;
	border-radius: 0;
	background-color: transparent;
	box-shadow: none;
	font-weight: normal;
	font-size: var(--default-font-size);
	line-height: var(--default-clickable-area);

	.material-design-icon {
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
	}
}
</style>
