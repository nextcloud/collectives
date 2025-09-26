<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="t('collectives', 'Pick a template')" size="normal" @closing="onClose">
		<!-- Template list -->
		<ul class="templates-list">
			<li
				v-for="template in templateList"
				:key="template.id"
				class="template-item">
				<a
					:ref="templateRef(template.id)"
					href="#"
					class="template-item-link"
					@click="onCreate(template.id)">
					<div class="template-item-icon">
						<template v-if="template.emoji">
							{{ template.emoji }}
						</template>
						<PageIcon v-else :size="64" />
					</div>
					<span class="template-item-title">
						{{ template.title }}
					</span>
				</a>
			</li>
		</ul>
	</NcDialog>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { NcDialog } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import PageIcon from '../Icon/PageIcon.vue'
import pageMixin from '../../mixins/pageMixin.js'
import { usePagesStore } from '../../stores/pages.js'
import { useTemplatesStore } from '../../stores/templates.js'

export default {
	name: 'NewPageDialog',

	components: {
		NcDialog,
		PageIcon,
	},

	mixins: [
		pageMixin,
	],

	computed: {
		...mapState(usePagesStore, ['newPageParentId']),
		...mapState(useTemplatesStore, ['sortedTemplates']),

		templateList() {
			return [
				{
					emoji: null,
					id: null,
					title: t('collectives', 'Blank page'),
				},
				...this.sortedTemplates,
			]
		},

		templateRef() {
			return (templateId) => {
				return templateId === null
					? 'templateEmpty'
					: `template${templateId}`
			}
		},

		hasPreview() {
			return false
		},
	},

	mounted() {
		// Set initial focus to the empty template
		this.$nextTick(() => {
			this.$refs.templateEmpty[0].focus()
		})
	},

	methods: {
		...mapActions(usePagesStore, [
			'setNewPageParentId',
		]),

		onClose() {
			this.setNewPageParentId(null)
		},

		async onCreate(templateId) {
			try {
				await this.newPage(this.newPageParentId, templateId)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create new page'))
			} finally {
				this.onClose()
			}
		},
	},
}
</script>

<style scoped lang="scss">
:deep(.modal-container) {
	height: calc(100vw - 120px) !important;
	max-height: 500px !important;
}

.templates-list {
	--icon-size: 160px;
	--icon-gap: calc(4 * var(--default-grid-baseline));
	--icon-border: 2px;
	--icon-fullwidth: calc(var(--icon-size) + var(--icon-gap) + 2 * var(--icon-border));

	display: grid;
	height: 100%;
	align-items: center;

	grid-gap: var(--icon-gap);
	grid-auto-columns: 1fr;
	// We want maximum 5 columns. Putting 6 as we don't count the grid gap. So it will always be lower than 6
	max-width: calc(var(--icon-fullwidth) * 6);
	grid-template-columns: repeat(auto-fit, var(--icon-fullwidth));
	// Make sure all rows are the same height
	grid-auto-rows: 1fr;
	// center the columns set
	justify-content: center;

	.template-item {
		// Leave enough space for long titles that take two lines
		height: 220px;
		display: flex;

		&-link {
			display: flex;
			flex-direction: column;
			align-items: center;
		}

		&-icon {
			display: flex;
			align-items: center;
			align-content: center;
			justify-content: center;

			width: var(--icon-size);
			min-height: var(--icon-size);
			max-height: var(--icon-size);
			border: var(--icon-border) solid var(--color-border);
			border-radius: var(--border-radius-large);

			font-size: 36px;

			&:hover, &:focus, &:active {
				border-color: var(--color-primary-hover);
			}
		}

		&-title {
			max-width: calc(var(--icon-size) + 2 * var(--icon-border));
			padding: calc(var(--icon-gap) / 2);

			// Ellipsize long titles after two lines
			overflow: hidden;
			display: -webkit-box;
			line-clamp: 2;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
		}
	}
}
</style>
