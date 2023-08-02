<template>
	<div class="widget-heading" :class="{'first': first}">
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
		first: {
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style lang="scss" scoped>
.widget-heading {
	padding: 36px 0 12px 0;
	font-size: 20px;
	font-weight: bold;
	color: var(--color-text-maxcontrast);

	&.first {
		padding-top: 24px;
	}
}
</style>
