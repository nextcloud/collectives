<template>
	<div v-if="matches !== null" id="searchDialog__container">
		<div id="searchDialog__main">
			<span>Found {{ matches.length }} matches</span>
		</div>
	</div>
</template>

<script>
import { subscribe } from '@nextcloud/event-bus'

export default {
	name: 'SearchDialog',

	components: {},

	data() {
		return {
			matches: null,
		}
	},

	created() {
		subscribe('text:editor:search-start', ({ matches }) => {
			this.matches = matches
		})
	},
}
</script>

<style scoped>
#searchDialog__container {
	max-width: var(--text-editor-max-width);
	height: 50px;
	margin: auto;
	display: flex;
	flex-flow: row nowrap;
	justify-content: center;
}
</style>
