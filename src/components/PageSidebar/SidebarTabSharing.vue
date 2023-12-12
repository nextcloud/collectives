<template>
	<div class="shares-container">
		<!-- loading -->
		<NcEmptyContent v-if="loading('shares')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- shares list -->
		<ul v-else class="sharing-list">
			<SharingEntryLink v-if="!shares.length" />
			<SharingEntryLink v-for="(share, index) in shares"
				v-else
				:key="share.id"
				:index="index + 1"
				:share="share" />
		</ul>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import { NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import SharingEntryLink from './SharingEntryLink.vue'

export default {
	name: 'SidebarTabSharing',

	components: {
		NcEmptyContent,
		NcLoadingIcon,
		SharingEntryLink,
	},

	props: {
		pageId: {
			type: Number,
			required: true,
		},
	},

	computed: {
		...mapGetters([
			'isLandingPage',
			'loading',
			'sharesByPageId',
		]),

		shares() {
			return this.isLandingPage
				? this.sharesByPageId(0)
				: this.sharesByPageId(this.pageId)
		},
	},
}
</script>
