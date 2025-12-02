<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="shouldShow" class="search-dialog-container">
		<div class="search-dialog__buttons">
			<NcButton
				alignment="center-reverse"
				variant="tertiary"
				:aria-label="t('collectives', 'Clear search')"
				@click="clearSearch">
				<template #icon>
					<Close :size="20" />
				</template>
				{{ t('collectives', 'Clear search') }}
			</NcButton>

			<NcButton
				alignment="center-reverse"
				:aria-label="t('collectives', 'Find previous match')"
				@click="previous">
				<template #icon>
					<ArrowUp :size="20" />
				</template>
				{{ t('collectives', 'Find previous') }}
			</NcButton>

			<NcButton
				alignment="center-reverse"
				:aria-label="t('collectives', 'Find next match')"
				@click="next">
				<template #icon>
					<ArrowDown :size="20" />
				</template>
				{{ t('collectives', 'Find next') }}
			</NcButton>
		</div>

		<div class="search-dialog__info">
			<span v-if="matchAll">
				{{ t('collectives', 'Found {matches} matches for "{query}"', {
					matches: results.totalMatches ?? 0,
					query: searchQuery,
				}) }}
			</span>

			<span v-else>
				{{ t('collectives', 'Match {index} of {matches} for "{query}"', {
					index: results.matchIndex + 1,
					matches: results.totalMatches,
					query: searchQuery,
				}) }}
			</span>
		</div>

		<div class="search-dialog__highlight-all">
			<NcCheckboxRadioSwitch v-model="isHighlightAllChecked">
				{{ t('collectives', 'Highlight all matches') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import Close from 'vue-material-design-icons/Close.vue'
import { usePagesStore } from '../../stores/pages.js'
import { useSearchStore } from '../../stores/search.js'

export default {
	name: 'SearchDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		ArrowDown,
		ArrowUp,
		Close,
	},

	props: {
		show: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		...mapState(usePagesStore, ['isTextEdit']),
		...mapState(useSearchStore, [
			'searchQuery',
			'matchAll',
			'results',
		]),

		isHighlightAllChecked: {
			get() {
				return this.matchAll
			},

			set() {
				this.toggleMatchAll()
			},
		},

		shouldShow() {
			return this.show && this.results.totalMatches !== null
		},
	},

	methods: {
		t,
		...mapActions(useSearchStore, [
			'setSearchQuery',
			'toggleMatchAll',
			'showSearchDialog',
			'searchNext',
			'searchPrevious',
		]),

		previous() {
			this.searchPrevious()
			this.scrollIntoView()
		},

		next() {
			this.searchNext()
			this.scrollIntoView()
		},

		clearSearch() {
			this.setSearchQuery('')
			this.showSearchDialog(false)
		},

		getActiveTextElement() {
			return this.isTextEdit
				? document.querySelector('[data-collectives-el="editor"]')
				: document.querySelector('[data-collectives-el="reader"]')
		},

		scrollIntoView() {
			this.getActiveTextElement()
				.querySelector('[data-text-el="search-decoration"]')?.scrollIntoView({ block: 'center' })
		},
	},
}
</script>

<style lang="scss" scoped>
$button-gap: calc(var(--default-grid-baseline) * 3);

.search-dialog-container {
	width: 100%;
	display: flex;
	align-items: center;
	background-color: var(--color-main-background);
}

@media print {
	.search-dialog-container {
		display: none;
	}
}

.search-dialog__info {
	margin: 0 calc(var(--default-grid-baseline) * 6);
	font-weight: bold;
}

.search-dialog__buttons {
	display: flex;
	overflow: hidden;
	align-items: center;
	column-gap: $button-gap;
}

.search-dialog__highlight-all {
	margin-left: auto;
	margin-right: $button-gap;
	margin-top: $button-gap;
	margin-bottom: $button-gap;
	display: flex;
	align-items: center;
	column-gap: $button-gap;
}
</style>
