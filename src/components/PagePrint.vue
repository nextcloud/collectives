<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="page-container sheet-view">
		<div class="page-title-wrapper" :class="{ 'page-title-wrapper--subpage': page.parentId !== 0 }">
			<h2 v-if="page.parentId === 0" class="page-title page-title-collective">
				{{ currentCollectiveTitle }}
			</h2>
			<h2 v-else class="page-title">
				{{ pageTitleString }}
			</h2>
		</div>
		<div
			v-show="!loadFailed"
			ref="readerEl"
			class="sheet-view"
			data-collectives-el="reader"
			data-cy-collectives="reader" />
		<NcNoteCard
			v-if="loadFailed"
			type="error"
			:text="t('collectives', 'Could not load the content of this page.')" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import { ref } from 'vue'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { useReader } from '../composables/useReader.ts'
import pageContentMixin from '../mixins/pageContentMixin.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'

export default {
	name: 'PagePrint',

	components: {
		NcNoteCard,
	},

	mixins: [
		pageContentMixin,
	],

	props: {
		page: {
			required: true,
			type: Object,
		},
	},

	emits: [
		'failed',
		'loading',
		'ready',
	],

	setup() {
		const content = ref('')
		const { reader, readerEl, setupReader } = useReader(content)
		return { content, reader, readerEl, setupReader }
	},

	data() {
		return {
			loadFailed: false,
		}
	},

	computed: {
		...mapState(useCollectivesStore, ['currentCollectiveTitle']),
		...mapState(usePagesStore, ['pageDavUrl']),

		pageTitleString() {
			return this.page.emoji ? `${this.page.emoji} ${this.page.title}` : this.page.title
		},
	},

	mounted() {
		this.setupReader(this.page).then(() => {
			this.getPageContent().then(() => {
				this.$emit('ready')
			})
		})
	},

	methods: {
		t,

		async getPageContent() {
			const content = await this.fetchPageContent(this.pageDavUrl(this.page))
			if (content === null) {
				this.loadFailed = true
				this.$emit('failed')
				return
			}
			this.content = content
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../css/editor.scss';

.page-title-wrapper {
	// Same width and padding as the page text below, so title and text line up.
	// The width is based on the font size, so it is set here instead of on the
	// larger title font, where it would come out too wide.
	box-sizing: border-box;
	width: min(var(--text-editor-max-width, var(--text-editor-max-width-default)), 100%);
	margin-inline: auto;
	padding-inline: 14px;

	@media print {
		// Page content spans the full width when printing
		width: 100%;
	}

	&--subpage {
		break-before: page;
	}
}

.page-title {
	max-width: unset;
	margin: unset;

	padding-block: 8px 2px;
	padding-inline: 0;

	&-collective {
		font-size: 35px;
	}
}

[data-collectives-el="reader"] {
	max-width: unset !important;
	margin: unset !important;
}

:deep(.ProseMirror) {
	// Trailing padding may overflow onto an otherwise empty sheet
	padding-block: 0 !important;
}

:deep(.text-menubar) {
	display: none;
}

:deep(.editor__toc-container) {
	// Hide outline + table of contents
	display: none !important;
}
</style>
