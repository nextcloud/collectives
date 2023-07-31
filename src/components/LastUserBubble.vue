<template>
	<div>
		<template v-if="showPrefixString">
			{{ t('collectives', 'Last changed by') }}
		</template>
		<NcUserBubble :display-name="lastUserDisplayName"
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
		lastUserDisplayName: {
			type: String,
			required: true,
		},
		timestamp: {
			type: Number,
			required: true,
		},
		showPrefixString: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		lastEditedUserMessage() {
			return t('collectives', 'Last edited by {user}', { user: this.lastUserDisplayName })
		},

		lastUpdate() {
			return moment.unix(this.timestamp).fromNow()
		},
	},
}
</script>
