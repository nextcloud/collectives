<template>
	<div class="text-menubar">
		<div v-if="currentPage.lastUserId" class="infobar-item infobar-lastupdate">
			<div class="item-text">
				<LastUserBubble :last-user-id="currentPage.lastUserId"
					:last-user-display-name="currentPage.lastUserDisplayName"
					:timestamp="currentPage.timestamp" />
			</div>
		</div>
	</div>
</template>

<script>
import LastUserBubble from '../LastUserBubble.vue'

export default {
	name: 'PageInfoBar',

	components: {
		LastUserBubble,
	},

	props: {
		currentPage: {
			type: Object,
			required: true,
		},
	},
}
</script>

<style scoped lang="scss">
.text-menubar {
	--background-blur: blur(10px);
	position: sticky;
	top: 59px;
	z-index: 10021;
	background-color: var(--color-main-background-translucent);
	backdrop-filter: var(--background-blur);
	height: 44px;
	padding: 12px 8px 4px 14px;
	display: flex;
	max-width: 670px;
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
