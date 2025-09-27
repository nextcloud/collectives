<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li
		class="tag"
		:class="{
			color: tag.color,
			'enough-contrast': enoughContrast,
		}"
		:style="style">
		<a
			href="#"
			@keydown.enter.prevent.stop="$emit('select')"
			@click="$emit('select')">
			{{ tag.name }}
		</a>
		<NcButton
			v-if="canRemove"
			:aria-label="t('collectives', 'Remove tag')"
			variant="tertiary"
			class="remove-button"
			@keydown.enter.prevent.stop="$emit('remove')"
			@click="$emit('remove')">
			<template #icon>
				<CloseIcon :size="20" />
			</template>
		</NcButton>
	</li>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import { useColor } from '../composables/useColor.js'

export default {
	name: 'PageTag',

	components: {
		CloseIcon,
		NcButton,
	},

	props: {
		tag: {
			type: Object,
			required: true,
		},

		canRemove: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		const { hasContrastToBackground } = useColor()
		return { hasContrastToBackground }
	},

	computed: {
		enoughContrast() {
			return this.tag.color && this.hasContrastToBackground(this.tag.color)
		},

		style() {
			return this.tag.color
				? `--page-tag-color: #${this.tag.color}`
				: null
		},
	},
}
</script>

<style lang="scss" scoped>
li {
	display: flex;
	padding: 2px 8px;
	font-size: 13px;
	border: 1px solid;
	border-radius: var(--border-radius-pill);
	border-color: var(--color-border);
	width: fit-content;
	max-width: 130px;

	&.color {
		padding-block: 1px;
		border-color: var(--page-tag-color);
		border-width: 2px;

		&.enough-contrast a {
			color: var(--page-tag-color);
		}
	}

	a {
		color: var(--color-text-maxcontrast);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.remove-button {
		min-height: 20px;
		min-width: 20px;
		height: 20px;
		width: 20px !important;
		padding: 7px;
		margin-left: 10px;
		color: var(--color-text-maxcontrast);
	}

	&:hover, &:active, &:focus {
		background-color: var(--color-background-hover);
	}
}
</style>
