<template>
	<div>
		<UserBubble :display-name="lastUserId"
			:user="lastUserId"
			:show-user-status="false">
			{{ lastEditedUserMessage }}
		</UserBubble>
		{{ lastUpdate }}
	</div>
</template>

<script>
import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'
import moment from '@nextcloud/moment'

export default {
	name: 'LastUserBubble',

	components: {
		UserBubble,
	},

	props: {
		lastUserId: {
			type: String,
			required: true,
		},
		timestamp: {
			type: Number,
			required: true,
		},
	},

	computed: {
		lastEditedUserMessage() {
			return t('collectives', 'Last edited by {user}', { user: this.lastUserId })
		},

		lastUpdate() {
			return moment.unix(this.timestamp).fromNow()
		},
	},
}
</script>

<style scoped>

</style>
