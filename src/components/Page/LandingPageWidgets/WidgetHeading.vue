<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="widget-heading">
		{{ title }}
	</div>
</template>

<script>
export default {
	name: 'WidgetHeading',

	props: {
		title: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
.widget-heading {
	padding: 24px 0 12px 0;
	font-weight: bold;
	color: var(--color-main-text);
}
</style>
