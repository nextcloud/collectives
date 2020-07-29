<template>
	<AppContentList :show-details="showDetails">
		<router-link v-for="page in pages"
			:key="page.title"
			:to="`/${$route.params.selectedWiki}/${page.title}`"
			:class="{active: isActive(page)}"
			class="app-content-list-item">
			<div class="app-content-list-item-icon" :style="iconStyle(page.title)">
				{{ page.title[0] }}
			</div>
			<div class="app-content-list-item-line-one">
				{{ page.title }}
			</div>
		</router-link>
	</AppContentList>
</template>

<script>

import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'

export default {
	name: 'PagesList',

	components: {
		AppContentList,
	},

	props: {
		pages: {
			type: Array,
			required: true,
		},
		currentPage: {
			type: Object,
			required: false,
			default: null,
		},
		loading: {
			type: Boolean,
			required: true,
		},
		showDetails: {
			type: Boolean,
			required: true,
		},
	},

	methods: {
		isActive(page) {
			return this.currentPage && this.currentPage.id === page.id
		},

		iconStyle(title) {
			const c = title.toRgb()
			return `background-color: rgb(${c.r}, ${c.g}, ${c.b})`
		},
	},
}
</script>
