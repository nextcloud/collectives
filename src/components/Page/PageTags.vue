<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="page-tags-container"
		:class="{
			'full-width-view': isFullWidth,
			'sheet-view': !isFullWidth,
		}">
		<ul class="page-tags"
			:class="{
				'full-width-view': isFullWidth,
				'sheet-view': !isFullWidth,
			}">
			<li v-for="tag in pageTagsVisible"
				:key="tag.id"
				:class="{
					'color': tag.color,
					'enough-contrast': tag.color && hasContrastToBackground(tag.color),
				}"
				:style="tag.color ? `--page-tag-color: #${tag.color}` : null">
				{{ tag.name }}
			</li>

			<li v-if="pageTagsInvisible.length > 0" :title="pageTagsInvisibleTitle">
				+ {{ pageTagsInvisible.length }}
			</li>
		</ul>
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { usePagesStore } from '../../stores/pages.js'
import { useTagsStore } from '../../stores/tags.js'
import { useColor } from '../../composables/useColor.js'

const TAGS_LIMIT = 5

export default {
	name: 'PageTags',

	props: {
		isFullWidth: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const { hasContrastToBackground } = useColor()
		return { hasContrastToBackground }
	},

	computed: {
		...mapState(usePagesStore, ['currentPage']),
		...mapState(useTagsStore, ['tags']),

		pageTagsDecorated() {
			return this.currentPage.tags
				// Replace tagIds by their respective tags
				.map(tagId => this.tags.find(t => t.id === tagId))
				// Filter out undefined (if tag got removed)
				.filter(Boolean)
		},

		pageTagsVisible() {
			return this.pageTagsDecorated.slice(0, TAGS_LIMIT)
		},

		pageTagsInvisible() {
			return this.pageTagsDecorated.slice(TAGS_LIMIT, this.pageTagsDecorated.length)
		},

		pageTagsInvisibleTitle() {
			return this.pageTagsInvisible.map(t => t.name).join(', ')
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

	li {
		padding: 2px 8px;
		font-size: 13px;
		border: 1px solid;
		border-radius: var(--border-radius-pill);
		border-color: var(--color-border);
		color: var(--color-text-maxcontrast);
		max-width: 150px;

		&.color {
			padding-block: 1px;
			border-color: var(--page-tag-color);
			border-width: 2px;

			&.enough-contrast {
				color: var(--page-tag-color);
			}
		}

		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}
</style>
