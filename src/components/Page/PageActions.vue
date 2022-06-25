<template>
	<Actions>
		<ActionLink :href="filesUrl"
			icon="icon-files-dark"
			:close-after-click="true">
			{{ t('collectives', 'Show in Files') }}
		</ActionLink>
		<ActionButton v-if="!isTemplatePage"
			class="action-button__template"
			:close-after-click="true"
			@click="editTemplate(currentPage.id)">
			<template #icon>
				<PagesTemplateIcon :size="14" decorative />
			</template>
			{{ editTemplateString }}
		</ActionButton>
		<ActionButton v-if="!landingPage"
			:close-after-click="true"
			:disabled="hasSubpages"
			@click="deletePage(currentPage.parentId, currentPage.id)">
			<template #icon>
				<DeleteIcon :size="20" decorative />
			</template>
			{{ deletePageString }}
		</ActionButton>
	</Actions>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'

import { mapGetters } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import DeleteIcon from 'vue-material-design-icons/Delete'
import PagesTemplateIcon from '../Icon/PagesTemplateIcon.vue'
import pageMixin from '../../mixins/pageMixin.js'

export default {
	name: 'PageActions',

	components: {
		Actions,
		ActionButton,
		ActionLink,
		DeleteIcon,
		PagesTemplateIcon,
	},

	mixins: [
		pageMixin,
	],

	computed: {
		...mapGetters([
			'currentPage',
			'isTemplatePage',
			'landingPage',
			'templatePage',
			'visibleSubpages',
		]),

		filesUrl() {
			return generateUrl(`/apps/files/?fileid=${this.currentPage.id}`)
		},

		hasTemplate() {
			return !!this.templatePage(this.currentPage.id)
		},

		hasSubpages() {
			return !!this.visibleSubpages(this.currentPage.id).length || !!this.hasTemplate
		},

		editTemplateString() {
			return this.hasTemplate
				? t('collectives', 'Edit template for subpages')
				: t('collectives', 'Add template for subpages')
		},

		deletePageString() {
			return this.hasSubpages
				? t('collectives', 'Cannot delete page with subpages')
				: this.isTemplatePage
					? t('collectives', 'Delete template')
					: t('collectives', 'Delete page')
		},
	},
}
</script>
