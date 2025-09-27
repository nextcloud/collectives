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
				v-bind="{ value }"
				class="title"
				:class="{ mobile: isMobile }"
				:disabled="disabled"
				:title="titleIfTruncated(value)"
				:placeholder="placeholder"
				@input="$emit('input', $event.target.value)"
				@keydown.stop="onKeyDown"
				@blur="$emit('blur')">
		</h2>
	</form>
</template>

<script>
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'

export default {
	name: 'PageTitle',

	props: {
		value: {
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
		value() {
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
