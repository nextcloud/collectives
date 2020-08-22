<template>
	<AppContentList>
		<div class="app-content-list-item">
			<button class="primary"
				:disabled="$store.state.loading"
				@click="$emit('newPage')">
				<span class="icon icon-add-white" />
				{{ t('collectives', 'Add a page') }}
			</button>
		</div>
		<router-link v-for="page in pages"
			:key="page.title"
			:to="`/${collectiveParam}/${page.title}`"
			:class="{active: isActive(page)}"
			class="app-content-list-item">
			<div class="app-content-list-item-icon"
				:style="iconStyle(`Page-${page.id}`)">
				{{ firstGrapheme(page.title) }}
			</div>
			<div class="app-content-list-item-line-one">
				{{ page.title }}
			</div>
			<span class="app-content-list-item-details"
				:class="{active: recentlyEdited(page)}">
				{{ lastUpdate(page) }}
				<Avatar v-if="page.user && page.user !== OC.currentUser"
					:user="page.user"
					size="16" />
			</span>
		</router-link>
	</AppContentList>
</template>

<script>

import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import moment from '@nextcloud/moment'

export default {
	name: 'PagesList',

	components: {
		AppContentList,
		Avatar,
	},

	computed: {
		collectiveParam() {
			return this.$store.getters.collectiveParam
		},

		pages() {
			return this.$store.state.pages
		},

		currentPage() {
			return this.$store.getters.currentPage
		},
	},

	methods: {
		isActive(page) {
			return this.currentPage && this.currentPage.id === page.id
		},

		iconStyle(id) {
			const c = id.toRgb()
			return `background-color: rgb(${c.r}, ${c.g}, ${c.b})`
		},

		lastUpdate(page) {
			return moment.unix(page.timestamp).fromNow()
		},

		// was edited in the last 5 Minutes
		recentlyEdited(page) {
			return (Date.now() / 1000) - page.timestamp < 300
		},

		// UTF8 friendly way of getting first 'letter'
		firstGrapheme(str) {
			return str[Symbol.iterator]().next().value
		},
	},
}
</script>

<style lang="scss" scoped>
	.app-content-list .app-content-list-item .app-content-list-item-icon {
		border-radius: 3px 12px 3px 3px;
		line-height: 40px;
		width: 30px;
		left: 12px;
	}

	.app-content-list .app-content-list-item .app-content-list-item-details.active {
		opacity: 1;
	}

	.app-content-list div.app-content-list-item:hover {
		background-color: var(--color-main-background);
	}

	.app-content-list div.app-content-list-item {
		cursor: default;
	}
</style>
