<template>
	<div v-if="matches !== null" class="search-dialog__container">
		<div class="search-dialog__main">
			<NcButton :aria-label="t('collectives', 'Find previous match')"
				@click="previousSearch">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
			</NcButton>

			<div style="margin: 0 40px;">
				Found {{ matches.length }} matches
			</div>

			<NcButton alignment="center-reverse"
				:aria-label="t('collectives', 'Find next match')"
				@click="nextSearch">
				<template #icon>
					<ArrowRight :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import { subscribe, emit } from '@nextcloud/event-bus'
import { NcButton } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { mapMutations } from 'vuex'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'

export default {
	name: 'SearchDialog',

	components: {
		NcButton,
		ArrowLeft,
		ArrowRight,
	},

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

	methods: {
		...mapMutations([
			'setSearchQuery',
		]),
		t,
		nextSearch() {
			emit('text:editor:search-next', {})
		},
		previousSearch() {
			emit('text:editor:search-previous', {})
		},
	},
}
</script>

<style scoped>
.search-dialog__container {
	width: 100%;
	height: 50px;
	display: flex;
	justify-content: center;
	align-items: center;
	position: sticky;
	bottom: 0;
	background-color: var(--color-main-background);
}

.search-dialog__main {
	display: flex;
	align-items: center;
}
</style>
