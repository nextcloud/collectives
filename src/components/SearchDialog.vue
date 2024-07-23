<template>
	<div id="searchDialog__container">
		<div id="searchDialog__main">
			<span v-if="matches">Found {{ matches.length }} matches</span>
			<span v-else>No matches found</span>
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
		subscribe('text:editor:search-start', ({ searchResults }) => {
			this.matches = searchResults
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