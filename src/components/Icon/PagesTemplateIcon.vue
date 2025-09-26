<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span
		:aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon collectives-pages-template-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			class="material-design-icon__svg"
			:width="size"
			:height="size"
			viewBox="0 0 18 25.5">
			<path d="M2,4H0v20.6c0,1.1,0.9,2,2,2h14v-2H2V4z" />
			<path d="M18,0H6C4.9,0,4,0.9,4,2v18.6c0,1.1,0.9,2,2,2h12c1.1,0,2-0.9,2-2V2C20,0.9,19.1,0,18,0z M17,6.3h-4v12h-2v-12H7v-2h10V6.3z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'PagesTemplateIcon',
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
