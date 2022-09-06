<template>
	<div>
		<NcUserBubble :display-name="lastUserId"
			:user="lastUserId"
			:show-user-status="false">
			{{ lastEditedUserMessage }}
		</NcUserBubble>
		{{ lastUpdate }}
	</div>
</template>

<script>
import { NcUserBubble } from '@nextcloud/vue'
import moment from '@nextcloud/moment'

export default {
	name: 'LastUserBubble',

	components: {
		NcUserBubble,
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
