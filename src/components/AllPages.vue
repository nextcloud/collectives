<template>
	<div class="allpages">
		<button class="close-button"
			:title="t('collectives', 'Close')"
			@click="hideAllPages">
			<span class="icon icon-close" />
		</button>
		<button class="print-button"
			:title="t('collectives', 'Print collective')"
			@click="printAllPages()">
			<span class="icon icon-category-office" />
			{{ t('collectives', 'Print') }}
		</button>
		<div v-for="page in subpages" :key="`page-${page.id}`" class="page-allpages">
			<h1 id="titleform-allpages" class="page-title-allpages">
				{{ pageTitle(page) }}
			</h1>
			<RichText :page-id="page.id"
				:page-url="pageUrl(page.id)" />
		</div>
	</div>
</template>

<script>
import RichText from './Page/RichText'

import { mapGetters, mapMutations } from 'vuex'

export default {
	name: 'AllPages',

	components: {
		RichText,
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'collectivePage',
			'pageDavUrl',
			'visiblePageTree',
		]),

		collectiveTitle() {
			const { emoji, name } = this.currentCollective
			return emoji ? `${emoji} ${name}` : name
		},

		subpages() {
			return this.visiblePageTree(this.collectivePage.id)
		},
	},

	methods: {
		...mapMutations(['hide', 'hideAllPages']),

		pageTitle(page) {
			return page.title === 'Readme' || page.title === '' ? this.collectiveTitle : page.title
		},

		pageUrl(pageId) {
			return this.pageDavUrl(pageId)
		},

		printAllPages() {
			window.print()
		},
	},
}
</script>

<style lang="scss" scoped>
#titleform-allpages {
	font-size: 35px;
	width: 100%;
	height: 43px;
	opacity: 0.8;
}

.page-title-allpages {
	padding: 8px 2px 2px 8px;
	margin: auto;
	max-width: 670px;
}

.app-content-details div #text-container {
	position: revert;
}

.allpages {
	margin-top: 8px;
}

.print-button, .close-button {
	position: relative;
	float: right;
	z-index: 10002;
	min-width: max-content;
	height: 44px;

	.icon {
		opacity: 1;
	}
}

.print-button .icon {
	margin-right: 8px;
}

@media print {
	div .page-allpages {
		page-break-after: always;
	}

	.print-button, .close-button {
		display: none !important;
	}
}
</style>
