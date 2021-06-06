<template>
	<button class="edit-button primary" @click="toggle">
		<span class="icon" :class="iconClass" />
		{{ t('collectives', edit ? 'Done' : 'Edit') }}
	</button>
</template>

<script>

export default {
	name: 'EditToggle',

	props: {
		edit: {
			type: Boolean,
			required: false,
		},
	},

	computed: {
		'iconClass'() {
			return this.edit ? 'icon-checkmark-white' : 'icon-rename-white'
		},
	},

	methods: {
		toggle() {
			this.$emit(this.edit ? 'stop' : 'start')
		},
	},
}
</script>

<style lang="scss">
	.edit-button {
		min-width: max-content;
		height: 44px;

		.icon {
			opacity: 1;
			margin-right: 8px;
		}
	}
</style>
