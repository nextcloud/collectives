<template>
	<NcAppContentList>
		<!-- loading -->
		<NcEmptyContent v-if="loading('backlinks')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- error message -->
		<NcEmptyContent v-else-if="error" :title="error">
			<template #icon>
				<AlertOctagonIcon />
			</template>
		</NcEmptyContent>

		<!-- backlinks list -->
		<template v-else-if="!loading('backlinks') && backlinks.length">
			<router-link v-for="backlinkPage in backlinks"
				:key="backlinkPage.id"
				:to="pagePath(backlinkPage)">
				<div class="app-content-list-item">
					<div class="app-content-list-item-icon">
						<PageIcon :size="26" fill-color="var(--color-main-background)" />
					</div>
					<div class="app-content-list-item-line-one">
						{{ pagePathTitle(backlinkPage) }}
					</div>
					<div class="app-content-list-item-line-two">
						{{ lastUpdate(page) }}
					</div>
				</div>
			</router-link>
		</template>

		<!-- no backlinks found -->
		<NcEmptyContent v-else
			:title="t('collectives', 'No backlinks available')"
			:description="t( 'collectives', 'If other pages link to this one, they will be listed here.')">
			<template #icon>
				<ArrowBottomLeftIcon />
			</template>
		</NcEmptyContent>
	</NcAppContentList>
</template>

<script>
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { NcAppContentList, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import AlertOctagonIcon from 'vue-material-design-icons/AlertOctagon.vue'
import ArrowBottomLeftIcon from 'vue-material-design-icons/ArrowBottomLeft.vue'
import PageIcon from '../Icon/PageIcon.vue'
import { GET_BACKLINKS } from '../../store/actions.js'

export default {
	name: 'SidebarTabBacklinks',

	components: {
		AlertOctagonIcon,
		NcAppContentList,
		NcEmptyContent,
		NcLoadingIcon,
		ArrowBottomLeftIcon,
		PageIcon,
	},

	props: {
		page: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			error: '',
		}
	},

	computed: {
		...mapState({
			backlinks: (state) => state.pages.backlinks,
		}),
		...mapGetters([
			'loading',
			'pagePath',
			'pagePathTitle',
		]),

		lastUpdate() {
			return (page) => moment.unix(page.timestamp).fromNow()
		},
	},

	watch: {
		'page.id'() {
			this.load('backlinks')
			this.unsetBacklinks()
			this.getBacklinks()
		},
	},

	mounted() {
		this.getBacklinks()
	},

	methods: {
		...mapMutations(['load', 'unsetBacklinks']),

		...mapActions({
			dispatchGetBacklinks: GET_BACKLINKS,
		}),

		/**
		 * Get backlinks for a page
		 */
		async getBacklinks() {
			try {
				this.dispatchGetBacklinks(this.page)
			} catch (e) {
				this.error = t('collectives', 'Could not get page backlinks')
				console.error('Failed to get page backlinks', e)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.app-content-list {
	max-width: none;
	border-right: none;
}

.app-content-list-item {
	border-bottom: 1px solid rgba(100, 100, 100, 0.1);
}

.app-content-list-item .app-content-list-item-icon {
	display: flex;
	line-height: 40px;
	width: 26px;
	height: 34px;
	left: 12px;
	font-size: 24px;
	background-color: var(--color-background-darker);
	border-radius: 4px;
}

.app-content-list .app-content-list-item .app-content-list-item-line-one {
	font-size: 120%;

	overflow: hidden;
	text-overflow: ellipsis;

	// Crop the string at the beginning, not end
	// TODO: Untested with RTL script
	text-align: left;
	direction: rtl;
}

.app-content-list .app-content-list-item .app-content-list-item-line-two {
	opacity: .5;
}

.app-content-list-item:hover {
	background-color: var(--color-background-hover);
}
</style>
