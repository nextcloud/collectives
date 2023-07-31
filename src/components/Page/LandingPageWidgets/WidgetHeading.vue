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

<style scoped>
.widget-heading {
	padding: 30px 0 12px 0;
	font-size: 20px;
	font-weight: bold;
	color: var(--color-text-maxcontrast);
}
</style>
