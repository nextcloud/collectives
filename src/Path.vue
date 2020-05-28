<template>
	<div>
		Hi {{ name }}!
	</div>
</template>

<script>
export default {
	name: 'Path',
	props: {
		name: {
			type: String,
			required: true,
		},
	},
}
</script>
