<template>
	<div>
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
		...mapMutations(['hide']),

		pageTitle(page) {
			return page.title === 'Readme' || page.title === '' ? this.collectiveTitle : page.title
		},

		pageUrl(pageId) {
			return this.pageDavUrl(pageId)
		},
	},
}
</script>

<style scoped>
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

@media print {
	div .page-allpages {
		page-break-after: always;
	}
}
</style>
