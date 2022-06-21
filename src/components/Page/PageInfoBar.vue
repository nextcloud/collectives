<template>
	<div class="text-menubar">
		<div v-if="currentPage.lastUserId" class="infobar-item infobar-lastupdate">
			<div class="item-text">
				<UserBubble :display-name="currentPage.lastUserId"
					:user="currentPage.lastUserId"
					:show-user-status="false">
					{{ lastEditedUserMessage }}
				</UserBubble>
				{{ lastUpdate }}
			</div>
		</div>
	</div>
</template>

<script>
import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'
import moment from '@nextcloud/moment'

export default {
	name: 'PageInfoBar',

	components: {
		UserBubble,
	},

	props: {
		currentPage: {
			type: Object,
			required: true,
		},
	},

	computed: {
		lastEditedUserMessage() {
			return t('collectives', 'Last edited by {user}', { user: this.currentPage.lastUserId })
		},

		lastUpdate() {
			return moment.unix(this.currentPage.timestamp).fromNow()
		},
	},
}
</script>

<style scoped lang="scss">
.text-menubar {
	--background-blur: blur(10px);
	position: sticky;
	top: 0;
	z-index: 10021;
	background-color: var(--color-main-background-translucent);
	backdrop-filter: var(--background-blur);
	height: 44px;
	padding: 12px 8px 4px 14px;
	display: flex;
	width: 670px;
	margin: auto;
	flex-wrap: nowrap;
	align-items: center;
	overflow: hidden;
}

.infobar-item {
	display: flex;
	flex-direction: row;
	flex-wrap: nowrap;
	align-items: center;
	overflow: hidden;
	white-space: nowrap;
	opacity: .5;
}

.infobar-seperator {
	padding: 0px 12px;
	opacity: .5;
}

.item-text {
	overflow: hidden;
	text-overflow: ellipsis;
}

@media print {
	.text-menubar {
		display: none !important;
	}
}
</style>
