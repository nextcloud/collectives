<template>
	<div v-if="totalMatches !== null" class="search-dialog__container">
		<div class="search-dialog__main">
			<div style="margin: 0 40px;">
				Found {{ totalMatches }} matches
			</div>

			<div class="search-dialog__buttons">
				<NcButton :aria-label="t('collectives', 'Find previous match')"
					@click="previousSearch">
					<template #icon>
						<ArrowUp :size="20" />
					</template>
				</NcButton>

				<NcButton alignment="center-reverse"
					:aria-label="t('collectives', 'Find next match')"
					@click="nextSearch">
					<template #icon>
						<ArrowDown :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { subscribe, emit } from '@nextcloud/event-bus'
import { NcButton } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { mapMutations } from 'vuex'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'

export default {
	name: 'SearchDialog',

	components: {
		NcButton,
		ArrowDown,
		ArrowUp,
	},

	data() {
		return {
			totalMatches: null,
		}
	},

	created() {
		subscribe('text:editor:search-results', ({ results }) => {
			this.totalMatches = results
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

.search-dialog__buttons {
	display: flex;
	justify-content: space-between;
}
</style>
