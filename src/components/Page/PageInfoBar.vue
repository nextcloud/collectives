<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="text-menubar">
		<div v-if="currentPage.lastUserId" class="infobar-item infobar-lastupdate">
			<div class="item-text">
				<LastUserBubble :last-user-id="currentPage.lastUserId"
					:last-user-display-name="currentPage.lastUserDisplayName"
					:timestamp="currentPage.timestamp"
					:show-prefix-string="true" />
			</div>
		</div>
	</div>
</template>

<script>
import LastUserBubble from '../LastUserBubble.vue'

export default {
	name: 'PageInfoBar',

	components: {
		LastUserBubble,
	},

	props: {
		currentPage: {
			type: Object,
			required: true,
		},
	},
}
</script>

<style scoped lang="scss">
.text-menubar {
	// Copied from `.text-menubar` in text app
	--background-blur: blur(10px);
	position: sticky;
	top: 0;
	bottom: var(--default-grid-baseline);
	width: 100%;
	z-index: 10021;
	background-color: var(--color-main-background-translucent);
	backdrop-filter: var(--background-blur);
	height: var(--default-clickable-area);
	border-bottom: 1px solid var(--color-border);
	padding-block: var(--default-grid-baseline);
	display: flex;
	align-items: center;

	overflow: hidden;
}

.infobar-item {
	display: flex;
	flex-direction: row;
	flex-wrap: nowrap;
	align-items: center;
	overflow: hidden;
	white-space: nowrap;
	opacity: .5;
}

.infobar-lastupdate {
	padding-inline-start: 14px;
}

.infobar-seperator {
	padding: 0px 12px;
	opacity: .5;
}

.item-text {
	overflow: hidden;
	text-overflow: ellipsis;
}

@media print {
	.text-menubar {
		display: none !important;
	}
}
</style>
