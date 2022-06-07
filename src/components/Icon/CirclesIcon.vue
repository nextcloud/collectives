<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon circles-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg :fill="fillColor"
			class="material-design-icon__svg"
			:width="size"
			:height="size"
			viewBox="0 0 21.33 21.33">
			<path d="M10.67 1.33a9.34 9.34 0 100 18.68 9.34 9.34 0 000-18.68zM6.93 15.8a2.33 2.33 0 110-4.67 2.33 2.33 0 010 4.67zm1.4-8.87a2.33 2.33 0 114.67 0 2.33 2.33 0 01-4.67 0zm6.07 8.87a2.33 2.33 0 110-4.67 2.33 2.33 0 010 4.67z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'CirclesIcon',
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
