<template>
	<div class="wiki-heading">
		<h2>
			<router-link :to="`/${name}`">
				{{ name }}
			</router-link>
		</h2>
		<button class="primary" @click="$emit('newPage')">
			<span class="icon icon-add-white" />
			{{ t('wiki', 'Add a page') }}
		</button>
	</div>
</template>

<script>
export default {
	name: 'WikiHeading',

	computed: {
		name() {
			return this.wiki.name
		},
		wiki() {
			return this.$store.getters.currentWiki
		},
	},
}
</script>

<style scoped>
.wiki-heading {
	position: absolute;
	left: 0;
	top: 0;
	padding: 7px 48px;
	display: flex;
	z-index: 10;
}
.wiki-heading button {
	align-self: center;
	display: flex;
	cursor: pointer;
	margin: 0 5px;
	min-height: 30px;
}
.wiki-heading h2 {
	display: flex;
	margin: 0 5px;
	cursor: pointer;
	opacity: 0.5;
	color: var(--color-main-text);
}
.wiki-heading h2:hover {
	opacity: 1;
}
</style>
