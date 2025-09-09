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
		<div ref="readerEl"
			class="sheet-view"
			data-collectives-el="reader"
			data-cy-collectives="reader" />
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { useCollectivesStore } from '../stores/collectives.js'
import pageContentMixin from '../mixins/pageContentMixin.js'
import { usePagesStore } from '../stores/pages.js'
import { useEditor } from '../composables/useEditor.js'

export default {
	name: 'PagePrint',

	mixins: [
		pageContentMixin,
	],

	props: {
		page: {
			required: true,
			type: Object,
		},
	},

	setup(props) {
		const { davContent, reader, readerEl, setupReader } = useEditor(props.page)
		return { davContent, reader, readerEl, setupReader }
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

		this.setupReader().then(() => {
			this.getPageContent().then(() => {
				this.$emit('ready')
			})
		})
	},

	methods: {
		async getPageContent() {
			this.davContent = await this.fetchPageContent(this.pageDavUrl(this.page))
			this.reader?.setContent(this.davContent)
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../css/editor';

.page-title {
	max-width: unset;
	margin: unset;

	padding-block: 8px 2px;

	&-collective {
		font-size: 35px;
	}

	&-subpage {
		page-break-before: always;
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
</style>
