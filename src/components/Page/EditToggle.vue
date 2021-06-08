<template>
	<button class="edit-button"
		:class="{ primary }"
		@click="toggle">
		<span class="icon" :class="iconClasses" />
		{{ t('collectives', edit ? 'Done' : 'Edit') }}
	</button>
</template>

<script>

export default {
	name: 'EditToggle',

	props: {
		edit: {
			type: Boolean,
			required: true,
		},
		primary: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		'iconClasses'() {
			return {
				'icon-checkmark': this.edit,
				'icon-rename': !this.edit,
				'icon-white': this.primary,
			}
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
		margin-top: 0px;

		.icon {
			opacity: 1;
			margin-right: 8px;
		}
	}
</style>
