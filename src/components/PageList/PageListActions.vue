<template>
	<div class="page-list-item-actions">
		<Actions :force-menu="true">
			<ActionButton v-if="!isTemplate"
				class="action-button-template"
				:close-after-click="true"
				@click="editTemplate(pageId)">
				<template #icon>
					<PagesTemplateIcon :size="14" />
				</template>
				{{ editTemplateString }}
			</ActionButton>
			<ActionButton v-if="!isLandingPage"
				:close-after-click="true"
				:disabled="hasSubpages"
				@click="deletePage(parentPageId, pageId)">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ deletePageString }}
			</ActionButton>
			<ActionSeparator v-if="lastUserId" />
			<li class="action action--user-bubble">
				<button class="action-button action-button--user-bubble" type="button">
					<ClockOutlineIcon :size="20" />
					<LastUserBubble :last-user-id="lastUserId" :timestamp="timestamp" />
				</button>
			</li>
		</Actions>
		<Actions>
			<ActionButton class="action-button-add" @click="newPage(pageId)">
				<template #icon>
					<PlusIcon :size="20" fill-color="var(--color-main-text)" />
				</template>
				{{ addPageString }}
			</ActionButton>
		</Actions>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline'
import DeleteIcon from 'vue-material-design-icons/Delete'
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

		deletePageString() {
			return this.hasSubpages
				? t('collectives', 'Cannot delete page with subpages')
				: this.isTemplate
					? t('collectives', 'Delete template')
					: t('collectives', 'Delete page')
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
