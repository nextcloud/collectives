<template>
	<div class="subpages">
		<div v-for="page in subpages" :key="`page-${page.id}`" class="subpage">
			<h2 class="subpage-title">
				<router-link :to="pagePath(page)">
					{{ page.title }}
				</router-link>
			</h2>
			<RichText :page-url="pageDavUrl(page)" />
			<Subpages :page-id="page.id" />
		</div>
	</div>
</template>

<script>
import RichText from './RichText'

import { mapGetters, mapMutations } from 'vuex'

export default {
	name: 'Subpages',

	components: {
		RichText,
	},

	props: {
		pageId: {
			type: Number,
			required: true,
		},
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentPage',
			'pageDavUrl',
			'pagePath',
			'visibleSubpages',
		]),

		collectiveTitle() {
			const { emoji, name } = this.currentCollective
			return emoji ? `${emoji} ${name}` : name
		},

		subpages() {
			return this.visibleSubpages(this.pageId)
		},
	},

	methods: {
		...mapMutations(['hide']),
	},
}
</script>

<style lang="scss" scoped>

.subpage-title {
	font-size: 30px;
	line-height: 45px;
	width: 100%;
	opacity: 0.8;
	padding: 8px 2px 2px 8px;
	margin: auto;
	max-width: 670px;
	font-weight: normal;
}

.app-content-details div #text-container {
	position: revert;
}

.subpages {
	margin-top: 8px;
}

.close-button {
	position: relative;
	float: right;
	z-index: 10002;
	min-width: max-content;
	height: 44px;

	.icon {
		opacity: 1;
	}
}

div .subpage {
	page-break-before: always;
}
</style>
