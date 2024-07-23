<template>
	<div v-if="totalMatches !== null" class="search-dialog__container">
		<div class="search-dialog__buttons">
			<NcButton alignment="center-reverse"
				type="tertiary"
				:aria-label="t('collectives', 'Clear search')"
				@click="clearSearch">
				<template #icon>
					<Close :size="20" />
				</template>
				{{ t('collectives', 'Clear search') }}
			</NcButton>

			<NcButton alignment="center-reverse"
				:aria-label="t('collectives', 'Find previous match')"
				@click="previousSearch">
				<template #icon>
					<ArrowUp :size="20" />
				</template>
				{{ t('collectives', 'Find previous') }}
			</NcButton>

			<NcButton alignment="center-reverse"
				:aria-label="t('collectives', 'Find next match')"
				@click="nextSearch">
				<template #icon>
					<ArrowDown :size="20" />
				</template>
				{{ t('collectives', 'Find next') }}
			</NcButton>
		</div>

		<div class="search-dialog__info">
			<span v-if="matchAll">
				{{ t('collectives', 'Found {matches} matches for "{query}"', {
					matches: totalMatches,
					query: searchQuery,
				}) }}
			</span>

			<span v-else>
				{{ t('collectives', 'Match {index} of {matches} for "{query}"', {
					index: matchIndex + 1,
					matches: totalMatches,
					query: searchQuery,
				}) }}
			</span>
		</div>

		<div class="search-dialog__highlight-all">
			<NcCheckboxRadioSwitch :checked.sync="highlightAll">
				{{ t('collectives', 'Highlight all matches') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import { subscribe } from '@nextcloud/event-bus'
import { NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { mapGetters, mapMutations, mapActions } from 'vuex'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
	name: 'SearchDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		ArrowDown,
		ArrowUp,
		Close,
	},

	data() {
		return {
			totalMatches: null,
			matchIndex: 0,
			highlightAll: true,
		}
	},

	computed: {
		...mapGetters([
			'searchQuery',
			'matchAll',
		]),
	},

	watch: {
		highlightAll() {
			if (this.highlightAll !== this.matchAll) {
				this.toggleMatchAll()
			}
		},
		matchAll(value) {
			if (this.highlightAll !== value) {
				this.highlightAll = value
			}
		},
	},

	created() {
		subscribe('text:editor:search-results', ({ results, index }) => {
			this.totalMatches = results
			this.matchIndex = index
		})
	},

	methods: {
		t,
		...mapMutations([
			'setSearchQuery',
			'nextSearch',
			'previousSearch',
		]),
		...mapActions([
			'toggleMatchAll',
			'clearSearch',
		]),
	},
}
</script>

<style lang="scss" scoped>
$button-gap: calc(var(--default-grid-baseline) * 3);

.search-dialog__container {
	width: 100%;
	height: 50px;
	display: flex;
	position: sticky;
	align-items: center;
	bottom: 0;
	background-color: var(--color-main-background);
}

.search-dialog__info {
	margin: 0 calc(var(--default-grid-baseline) * 6);
	font-weight: bold;
}

.search-dialog__buttons {
	display: flex;
	align-items: center;
	column-gap: $button-gap;
}

.search-dialog__highlight-all {
	margin-left: auto;
	margin-right: $button-gap;
	display: flex;
	align-items: center;
	column-gap: $button-gap;
}
</style>
