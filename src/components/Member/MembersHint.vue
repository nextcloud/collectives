<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="app-navigation-hint">
		{{ hint }}
	</div>
</template>

<script>
export default {
	name: 'MembersHint',

	props: {
		hint: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
.app-navigation-hint {
	color: var(--color-text-maxcontrast);
	line-height: var(--default-clickable-area);
	list-style-type:none;
	white-space: nowrap;
	text-overflow: ellipsis;
	box-shadow: none !important;
	user-select: none;
	pointer-events: none;
	padding-left: 10px;
}
</style>
