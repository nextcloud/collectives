<template>
	<div class="update" :class="{active: recentlyEdited}">
		<Avatar v-if="user"
			:user="user"
			:disable-menu="true"
			:show-user-status="false"
			:tooltip-message="lastEditedUserMessage"
			:size="16" />
		{{ lastUpdate }}
	</div>
</template>

<script>

import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import moment from '@nextcloud/moment'

export default {
	name: 'LastUpdate',

	components: {
		Avatar,
	},

	props: {
		user: {
			type: String,
			default: '',
		},
		timestamp: {
			type: Number,
			default: 0,
		},
	},

	computed: {

		lastEditedUserMessage() {
			const user = this.user
			return t('collectives', 'Last edited by {user}', { user })
		},

		lastUpdate() {
			return moment.unix(this.timestamp).fromNow()
		},

		// was edited in the last 5 Minutes
		recentlyEdited() {
			return (Date.now() / 1000) - this.timestamp < 300
		},
	},
}

</script>

<style lang="scss" scoped>

	.update {
		opacity: .5;
	}

	.update.active {
		opacity: 1;
	}

	div .avatardiv {
		vertical-align: text-bottom;
	}

</style>
