<template>
	<router-link
		:to="`/${collectiveParam}/${encodeURIComponent(page.title)}`"
		:class="{active: isActive}"
		class="app-content-list-item">
		<div class="app-content-list-item-icon"
			:style="iconStyle">
			{{ firstGrapheme }}
		</div>
		<div class="app-content-list-item-line-one">
			{{ page.title }}
		</div>
		<div class="app-content-list-item-line-two">
			{{ lastUpdate }}
		</div>
		<span class="app-content-list-item-details"
			:class="{active: recentlyEdited}">
			<Avatar v-if="page.lastUserId"
				:user="page.lastUserId"
				:disable-menu="true"
				:tooltip-message="lastEditedUserMessage"
				:size="20" />
		</span>
	</router-link>
</template>

<script>

import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import moment from '@nextcloud/moment'

export default {
	name: 'PagesListItem',

	components: {
		Avatar,
	},

	props: {
		page: {
			required: true,
			type: Object,
		},
	},

	computed: {
		collectiveParam() {
			return this.$store.getters.collectiveParam
		},

		currentPage() {
			return this.$store.getters.currentPage
		},

		isActive() {
			return this.currentPage && this.currentPage.id === this.page.id
		},

		iconStyle() {
			const id = `Page-${this.page.id}`
			const c = id.toRgb()
			return `background-color: rgb(${c.r}, ${c.g}, ${c.b})`
		},

		lastUpdate() {
			return moment.unix(this.page.timestamp).fromNow()
		},

		// was edited in the last 5 Minutes
		recentlyEdited() {
			return (Date.now() / 1000) - this.page.timestamp < 300
		},

		// UTF8 friendly way of getting first 'letter'
		firstGrapheme() {
			return this.page.title[Symbol.iterator]().next().value
		},

		lastEditedUserMessage() {
			return t('collectives',
				'Last edited by {user}', { user: this.page.lastUserId })
		},
	},
}

</script>

<style lang="scss" scoped>

	.app-content-list-item .app-content-list-item-icon {
		border-radius: 3px 12px 3px 3px;
		line-height: 40px;
		width: 30px;
		left: 12px;
	}

	.app-content-list-item .app-content-list-item-details.active {
		opacity: 1;
	}

	div.app-content-list-item:hover {
		background-color: var(--color-main-background);
	}

	div.app-content-list-item {
		cursor: default;
	}

</style>
