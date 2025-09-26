<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span
		:aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon collectives-page-template-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			class="material-design-icon__svg"
			:width="size"
			:height="size"
			viewBox="0 0 18 25.5">
			<path d="M15.8,0H2.3C1,0,0,1,0,2.3v21c0,1.2,1,2.3,2.3,2.3h13.5c1.2,0,2.3-1,2.3-2.3v-21C18,1,17,0,15.8,0z M14.6,7.1h-4.5v13.5H7.9 V7.1H3.4V4.9h11.2V7.1z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'PageTemplateIcon',
	props: {
		title: {
			type: String,
			default: '',
		},

		fillColor: {
			type: String,
			default: 'currentColor',
		},

		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
