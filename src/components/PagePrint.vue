<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="text-container" :key="'text-' + page.id" class="page sheet-view">
		<h2 v-if="page.parentId === 0" id="page-title-collective" class="page-title page-title-collective">
			{{ currentCollectiveTitle }}
		</h2>
		<h2 v-else class="page-title page-title-subpage">
			{{ pageTitleString }}
		</h2>
		<div ref="reader" class="sheet-view" data-collectives-el="reader" />
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { useCollectivesStore } from '../stores/collectives.js'
import editorMixin from '../mixins/editorMixin.js'
import pageContentMixin from '../mixins/pageContentMixin.js'
import { usePagesStore } from '../stores/pages.js'

export default {
	name: 'PagePrint',

	mixins: [
		editorMixin,
		pageContentMixin,
	],

	props: {
		page: {
			required: true,
			type: Object,
		},
	},

	data() {
		return {
			davContent: '',
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
@import '../css/editor';

.page-title {
	line-height: 45px;
	padding: 8px 2px 2px 8px;

	overflow: hidden;
	text-overflow: ellipsis;

	&-collective {
		font-size: 35px;
	}

	&-subpage {
		page-break-before: always;
		break-before: always;
	}
}

:deep(.text-menubar) {
	display: none;
}
</style>
