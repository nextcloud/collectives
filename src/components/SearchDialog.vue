<template>
	<div v-if="totalMatches !== null" class="search-dialog__container">
		<div class="search-dialog__info">
			Found {{ totalMatches }} matches
		</div>

		<div class="search-dialog__buttons">
			<NcButton alignment="center-reverse"
				:aria-label="t('collectives', 'Find previous match')"
				@click="previousSearch">
				<template #icon>
					<ArrowUp :size="20" />
				</template>
				{{ t('collectives', 'Find prev') }}
			</NcButton>

			<NcButton alignment="center-reverse"
				:aria-label="t('collectives', 'Find next match')"
				@click="nextSearch">
				<template #icon>
					<ArrowDown :size="20" />
				</template>
				{{ t('collectives', 'Find next') }}
			</NcButton>

			<NcButton alignment="center-reverse"
				type="tertiary"
				:aria-label="t('collectives', 'Find all')"
				:pressed="matchAll"
				@click="setMatchAll">
				<template #icon>
					<AllInclusive :size="20" />
				</template>
				{{ t('collectives', 'Find all') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { subscribe, emit } from '@nextcloud/event-bus'
import { NcButton } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { mapGetters, mapMutations } from 'vuex'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import AllInclusive from 'vue-material-design-icons/AllInclusive.vue'

export default {
	name: 'SearchDialog',

	components: {
		NcButton,
		ArrowDown,
		ArrowUp,
		AllInclusive,
	},

	data() {
		return {
			totalMatches: null,
			matchAll: true,
		}
	},

	computed: {
		...mapGetters([
			'searchQuery',
		]),
	},

	created() {
		subscribe('text:editor:search-results', ({ results }) => {
			this.totalMatches = results
		})
	},

	methods: {
		t,
		...mapMutations([
			'setSearchQuery',
		]),
		nextSearch() {
			this.matchAll = false
			emit('text:editor:search-next', {})
		},
		previousSearch() {
			this.matchAll = false
			emit('text:editor:search-previous', {})
		},
		setMatchAll() {
			this.matchAll = !this.matchAll

			this.setSearchQuery({
				query: this.searchQuery,
				matchAll: this.matchAll,
			})
		},
	},
}
</script>

<style scoped>
.search-dialog__container {
	width: 100%;
	height: 50px;
	display: flex;
	position: sticky;
	justify-content: space-between;
	align-items: center;
	bottom: 0;
	background-color: var(--color-main-background);
}

.search-dialog__info {
	font-weight: bold;
}

.search-dialog__buttons {
	display: flex;
	align-items: center;
	column-gap: calc(var(--default-grid-baseline) * 3);
}
</style>
