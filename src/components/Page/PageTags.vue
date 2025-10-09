<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="page-tags-container"
		:class="{
			'full-width-view': isFullWidth,
			'sheet-view': !isFullWidth,
		}">
		<ul
			class="page-tags"
			:class="{
				'full-width-view': isFullWidth,
				'sheet-view': !isFullWidth,
			}">
			<PageTag
				v-for="tag in pageTagsVisible"
				:key="tag.id"
				:title="pageTagTitle"
				:tag="tag"
				@select="onSelectTag(tag.id)" />

			<NcPopover v-if="pageTagsInvisible.length > 0" popup-role="listbox" no-focus-trap>
				<template #trigger="{ attrs }">
					<PageTag
						v-bind="attrs"
						:title="pageTagsInvisibleTitle"
						class="tag-invisible"
						:tag="{
							id: -1,
							name: `+ ${pageTagsInvisible.length}`,
							color: '',
						}" />
				</template>
				<template #default>
					<div class="page-tags-invisible-popover">
						<ul class="page-tags popover">
							<PageTag
								v-for="tag in pageTagsInvisible"
								:key="tag.id"
								:title="pageTagTitle"
								:tag="tag"
								@select="onSelectTag(tag.id)" />
						</ul>
					</div>
				</template>
			</NcPopover>
		</ul>
	</div>
</template>

<script>
import { NcPopover } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import PageTag from '../PageTag.vue'
import { usePagesStore } from '../../stores/pages.js'
import { useTagsStore } from '../../stores/tags.js'

const TAGS_LIMIT = 5

export default {
	name: 'PageTags',

	components: {
		NcPopover,
		PageTag,
	},

	props: {
		isFullWidth: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		...mapState(usePagesStore, ['currentPage']),
		...mapState(useTagsStore, ['tags']),

		pageTags() {
			return this.currentPage.tags
				// Replace tagIds by their respective tags
				.map((tagId) => this.tags.find((t) => t.id === tagId))
				// Filter out undefined (if tag got removed)
				.filter(Boolean)
		},

		pageTagsVisible() {
			return this.pageTags.slice(0, TAGS_LIMIT)
		},

		pageTagsInvisible() {
			return this.pageTags.slice(TAGS_LIMIT)
		},

		pageTagTitle() {
			return t('collectives', 'Filter page list by tag')
		},

		pageTagsInvisibleTitle() {
			return this.pageTagsInvisible.map((t) => t.name).join(', ')
		},
	},

	methods: {
		...mapActions(useTagsStore, ['addFilterTagId']),

		onSelectTag(tagId) {
			this.addFilterTagId(tagId)
		},
	},
}
</script>

<style scoped lang="scss">
.page-tags-container {
	display: flex;
	max-width: 100%;
	margin-bottom: var(--default-grid-baseline);
	padding: 0 8px;
	align-items: center;
	background-color: var(--color-main-background);

	&.sheet-view {
		margin-left: max(0px, calc(50% - (var(--text-editor-max-width) / 2)));
	}
}

.page-tags {
	display: flex;
	flex-direction: row;
	gap: 4px;
	max-width: 100%;

	&.sheet-view {
		max-width: var(--text-editor-max-width);
	}

	&.popover {
		flex-direction: column;
	}
}

.page-tags-invisible-popover {
	max-height: 200px;
	max-width: 140px;
	padding: var(--default-grid-baseline);
	overflow-y: auto;
}
</style>
