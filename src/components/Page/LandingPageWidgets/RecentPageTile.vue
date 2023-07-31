<template>
	<router-link :to="pagePath(page)" class="recent-page-tile">
		<div class="recent-page-tile__rectangle">
			<template v-if="isTemplate">
				<PageTemplateIcon :size="36" fill-color="var(--color-background-darker)" />
			</template>
			<template v-else-if="page.emoji">
				{{ page.emoji }}
			</template>
			<template v-else>
				<PageIcon :size="36" fill-color="var(--color-background-darker)" />
			</template>
		</div>
		<div class="recent-page-tile__title">
			{{ page.title }}
		</div>
		<LastUserBubble :last-user-id="page.lastUserId"
			:last-user-display-name="page.lastUserDisplayName"
			:timestamp="page.timestamp"
			class="recent-page-tile__last-user-bubble" />
	</router-link>
</template>

<script>
import { mapGetters } from 'vuex'
import LastUserBubble from '../../LastUserBubble.vue'
import PageTemplateIcon from '../../Icon/PageTemplateIcon.vue'
import PageIcon from '../../Icon/PageIcon.vue'

export default {
	name: 'RecentPageTile',
	components: {
		LastUserBubble,
		PageIcon,
		PageTemplateIcon,
	},

	props: {
		page: {
			type: Object,
			required: true,
		},
	},

	computed: {
		...mapGetters([
			'pagePath',
		]),

		isTemplate() {
			return this.page.title === 'Template'
		},
	},
}
</script>

<style lang="scss" scoped>
.recent-page-tile {
	margin-right: 12px;
	max-width: 150px;
	box-sizing: content-box;
	padding: 12px;

	border-radius: var(--border-radius-rounded);

	&:hover {
		background-color: var(--color-background-hover);
	}

	&__rectangle {
		display: flex;
		height: 150px;
		width: 150px;

		font-size: 36px;
		align-items: center;
		align-content: center;
		justify-content: center;
		background-color: var(--color-primary-element-light);
		border-radius: var(--border-radius-rounded);
	}

	&__title {
		margin-top: 12px;
		font-size: 20px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__last-user-bubble {
		margin-top: 8px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}
</style>
