<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ol class="publish-step-indicator">
		<li
			v-for="(step, index) in steps"
			:key="step"
			class="publish-step-indicator__step"
			:class="{
				'publish-step-indicator__step--done': index < current,
				'publish-step-indicator__step--current': index === current,
			}"
			:aria-current="index === current ? 'step' : undefined">
			<span class="publish-step-indicator__number">
				<CheckIcon v-if="index < current" :size="16" />
				<template v-else>
					{{ index + 1 }}
				</template>
			</span>
			{{ step }}
		</li>
	</ol>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'

export default {
	name: 'PublishStepIndicator',

	components: {
		CheckIcon,
	},

	props: {
		/** Translated step names */
		steps: {
			type: Array,
			required: true,
		},

		/** Index of the current step */
		current: {
			type: Number,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
.publish-step-indicator {
	display: flex;
	align-items: center;
	gap: 12px;
	margin: 0;
	padding: 8px 0;

	&__step {
		display: flex;
		align-items: center;
		gap: 8px;
		list-style: none;
		color: var(--color-text-maxcontrast);

		// Line between two steps
		& + &::before {
			content: '';
			width: 32px;
			border-top: 1px solid var(--color-border-maxcontrast);
		}

		&--current {
			color: var(--color-main-text);
			font-weight: bold;
		}
	}

	&__number {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 28px;
		height: 28px;
		border: 1px solid var(--color-border-maxcontrast);
		border-radius: 50%;
		font-size: var(--font-size-small, 13px);
	}

	&__step--done &__number,
	&__step--current &__number {
		border-color: var(--color-primary-element);
		background-color: var(--color-primary-element);
		color: var(--color-primary-element-text);
	}
}
</style>
