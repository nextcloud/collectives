<template>
	<div class="page-list-item-actions">
		<Actions :force-menu="true" @click.native.stop>
			<ActionButton v-if="!isTemplate"
				:close-after-click="true"
				class="action-button-template"
				@click.native="show('details')"
				@click="editTemplate(pageId)">
				<template #icon>
					<PagesTemplateIcon :size="14" decorative />
				</template>
				{{ editTemplateString }}
			</ActionButton>
			<ActionButton v-if="!isTemplate"
				:close-after-click="true"
				@click.native="show('details')"
				@click="gotoPageEmojiPicker">
				<template #icon>
					<EmoticonOutlineIcon :size="20" decorative />
				</template>
				{{ setEmojiString }}
			</ActionButton>
			<ActionButton v-if="!isLandingPage"
				:close-after-click="true"
				:disabled="hasSubpages"
				@click.native="show('details')"
				@click="deletePage(parentPageId, pageId)">
				<template #icon>
					<DeleteOffIcon v-if="hasSubpages" :size="20" decorative />
					<DeleteIcon v-else :size="20" decorative />
				</template>
				{{ deletePageString }}
			</ActionButton>
			<ActionSeparator v-if="lastUserId" />
			<li class="action action--user-bubble">
				<button class="action-button action-button--user-bubble" type="button">
					<ClockOutlineIcon :size="20" decorative />
					<LastUserBubble :last-user-id="lastUserId" :timestamp="timestamp" />
				</button>
			</li>
		</Actions>
		<Actions>
			<ActionButton class="action-button-add" @click="newPage(pageId)">
				<template #icon>
					<PlusIcon :size="20" fill-color="var(--color-main-text)" decorative />
				</template>
				{{ addPageString }}
			</ActionButton>
		</Actions>
	</div>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import { emit } from '@nextcloud/event-bus'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline'
import DeleteIcon from 'vue-material-design-icons/Delete'
import DeleteOffIcon from 'vue-material-design-icons/DeleteOff'
import EmoticonOutlineIcon from 'vue-material-design-icons/EmoticonOutline'
import PagesTemplateIcon from '../Icon/PagesTemplateIcon.vue'
import PlusIcon from 'vue-material-design-icons/Plus'
import LastUserBubble from '../LastUserBubble.vue'
import pageMixin from '../../mixins/pageMixin.js'

export default {
	name: 'PageListActions',

	components: {
		Actions,
		ActionButton,
		ActionSeparator,
		ClockOutlineIcon,
		DeleteIcon,
		DeleteOffIcon,
		EmoticonOutlineIcon,
		PagesTemplateIcon,
		PlusIcon,
		LastUserBubble,
	},

	mixins: [
		pageMixin,
	],

	props: {
		pageId: {
			type: Number,
			required: true,
		},
		pageUrl: {
			type: String,
			required: true,
		},
		parentPageId: {
			type: Number,
			required: true,
		},
		timestamp: {
			type: Number,
			required: true,
		},
		lastUserId: {
			type: String,
			required: true,
		},
		isLandingPage: {
			type: Boolean,
			default: false,
		},
		isTemplate: {
			type: Boolean,
			default: false,
		},
		hasTemplate: {
			type: Boolean,
			default: false,
		},
		hasSubpages: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		...mapGetters([
			'loading',
			'showTemplates',
		]),

		addPageString() {
			return this.isLandingPage
				? t('collectives', 'Add a page')
				: t('collectives', 'Add a subpage')
		},

		editTemplateString() {
			return this.hasTemplate
				? t('collectives', 'Edit template for subpages')
				: t('collectives', 'Add template for subpages')
		},

		setEmojiString() {
			return t('collective', 'Select emoji')
		},

		deletePageString() {
			return this.hasSubpages
				? t('collectives', 'Cannot delete page with subpages')
				: this.isTemplate
					? t('collectives', 'Delete template')
					: t('collectives', 'Delete page')
		},
	},

	methods: {
		...mapMutations(['show']),

		gotoPageEmojiPicker() {
			if (this.currentPage.id !== this.pageId) {
				this.$router.push(this.pageUrl)
			}
			this.$nextTick(() => {
				emit('toggle-page-emoji-picker', { open: true })
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.action-item--single {
	&.action-button-add {
		opacity: 1;
	}
}

.action--user-bubble {
	pointer-events: none;
}

.action-button--user-bubble {
	display: flex;
	align-items: flex-start;
	width: 100%;
	height: auto;
	margin: 0;
	padding: 0;
	padding-right: 14px;
	box-sizing: border-box;
	white-space: nowrap;
	opacity: .7;
	border: 0;
	border-radius: 0;
	background-color: transparent;
	box-shadow: none;
	font-weight: normal;
	font-size: var(--default-font-size);
	line-height: 44px;

	.material-design-icon {
		width: 44px;
		height: 44px;
	}
}
</style>
