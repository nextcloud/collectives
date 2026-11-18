<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="page-container sheet-view">
		<h2 v-if="page.parentId === 0" class="page-title page-title-collective">
			{{ currentCollectiveTitle }}
		</h2>
		<h2 v-else class="page-title page-title-subpage">
			{{ pageTitleString }}
		</h2>
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
		this.$emit('loading')

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

.page-title {
	max-width: unset;
	margin: unset;

	padding-block: 8px 2px;

	&-collective {
		font-size: 35px;
	}

	&-subpage {
		break-before: always;
	}
}

[data-collectives-el="reader"] {
	max-width: unset !important;
	margin: unset !important;
}

:deep(.text-menubar) {
	display: none;
}

:deep(.editor__toc-container) {
	// Hide outline + table of contents
	display: none !important;
}
</style>
