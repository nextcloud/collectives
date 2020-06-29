<template>
	<router-link v-if="useRouter" :to="to" :href="href" />
	<a v-else :href="href" />
</template>

<script>
export default {
	name: 'LinkView',
	props: ['node', 'updateAttrs', 'view'], // eslint-disable-line
	computed: {
		schema() { return this.view.state.schema },
		href() {
			return this.node.attrs.href
		},
		// TODO: use good heuristic
		useRouter() { return !this.href.startsWith('http') },
		to() { return this.href },
	},
}
</script>
