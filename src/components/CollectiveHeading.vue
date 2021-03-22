<template>
	<div class="collective-heading">
		<h2>
			<router-link :to="`/${collective.name}`"
				@click.native="$emit('toggleDetails')">
				<span v-if="collective.emoji" class="emoji">
					{{ collective.emoji }}
				</span>
				{{ collective.title }}
			</router-link>
		</h2>
	</div>
</template>

<script>
export default {
	name: 'CollectiveHeading',

	computed: {
		collective() {
			return this.$store.getters.currentCollective
		},
	},
}
</script>

<style scoped>
.collective-heading {
	position: absolute;
	left: 0;
	top: 0;
	padding: 7px 48px;
	display: flex;
	z-index: 10;
}
.collective-heading button {
	align-self: center;
	display: flex;
	cursor: pointer;
	margin: 0 5px;
	min-height: 30px;
}
.collective-heading h2 {
	display: flex;
	margin: 0 5px;
	cursor: pointer;
	opacity: 0.7;
	color: var(--color-main-text);
}
.collective-heading h2:hover {
	opacity: 1;
}
</style>
