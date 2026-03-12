<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<form @submit.prevent="$emit('submit')">
		<h2 class="page-title">
			<input
				ref="title"
				type="text"
				:value="modelValue"
				class="title"
				:class="{ mobile: isMobile }"
				:disabled="disabled"
				:title="titleIfTruncated(modelValue)"
				:placeholder="placeholder"
				@input="$emit('update:modelValue', $event.target.value)"
				@keydown.stop="onKeyDown"
				@blur="onBlur">
		</h2>
	</form>
</template>

<script>
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'

export default {
	name: 'PageTitle',

	props: {
		modelValue: {
			type: String,
			required: true,
		},

		disabled: {
			type: Boolean,
			default: false,
		},

		placeholder: {
			type: String,
			default: '',
		},
	},

	emits: [
		'blur',
		'update:modelValue',
		'save',
		'submit',
	],

	setup() {
		const isMobile = useIsMobile()
		return { isMobile }
	},

	data() {
		return {
			titleIsTruncated: false,
		}
	},

	computed: {
		titleIfTruncated() {
			return (title) => this.titleIsTruncated ? title : null
		},
	},

	watch: {
		modelValue() {
			this.$nextTick(() => {
				this.titleIsTruncated = this.$refs.title.scrollWidth > this.$refs.title.clientWidth
			})
		},
	},

	methods: {
		focus() {
			this.$refs.title.focus()
		},

		onKeyDown(event) {
			if ((event.ctrlKey || event.metaKey) && event.key === 's') {
				this.$emit('save')
				event.preventDefault()
			}
		},

		onBlur() {
			if (this.$.isMounted) {
				this.$emit('blur')
			}
		},
	},
}
</script>

<style scoped lang="scss">
form {
	flex: auto;
}

h2 {
	margin: 0;
	padding: 0;
}

input[type='text'] {
	border: none;
	color: var(--color-main-text);
	width: calc(100% - 2px);
	height: 43px;
	text-overflow: unset;
	font-size: inherit;
	font-weight: inherit;

	&.mobile {
		// Less padding to save some extra space
		padding: 0 4px 0 0;
	}

	&:disabled {
		opacity: 1;
	}

	&::placeholder {
		font-size: inherit;
		font-weight: inherit;
	}
}
</style>
