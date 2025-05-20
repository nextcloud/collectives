/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { useRootStore } from './root.js'
import { useCollectivesStore } from './collectives.js'
import { usePagesStore } from './pages.js'
import * as api from '../apis/collectives/index.js'
import { byTitle } from '../util/sortOrders.js'
import { TEMPLATE_PAGE } from '../constants.js'

export const useTemplatesStore = defineStore('templates', {
	state: () => ({
		templates: [],
		templatesLoaded: false,
	}),

	getters: {
		context() {
			const rootStore = useRootStore()
			const collectivesStore = useCollectivesStore()
			return {
				isPublic: rootStore.isPublic,
				collectiveId: collectivesStore.currentCollective?.id || collectivesStore.templatesCollectiveId,
				shareTokenParam: rootStore.shareTokenParam,
			}
		},

		sortedTemplates(state) {
			return state.templates.sort(byTitle)
		},

		hasTemplates(state) {
			return state.templates.length > 0
		},

		hasSubpages(state) {
			return (templateId) => {
				return state.templates.filter(p => p.parentId === templateId).length > 0
			}
		},

		rootTemplateId(state) {
			return state.hasTemplates
				? state.templates[0].parentId
				: 0
		},

		rootTemplates(state) {
			return state.sortedTemplates
				.filter(template => template.parentId === state.rootTemplateId)
		},

		currentPageFilePath(state) {
			return state.pageFilePath(state.currentPage)
		},

		templateFilePath: (_state) => (template) => {
			const pagesStore = usePagesStore()
			return pagesStore.pageFilePath(template)
		},
	},

	actions: {
		unsetTemplates() {
			this.templates = []
			this.templatesLoaded = false
		},

		_updateTemplatePage(template) {
			this.templates.splice(
				this.templates.findIndex(p => p.id === template.id),
				1,
				template,
			)
		},

		/**
		 * Get list of all templates for current collective
		 *
		 * @param {boolean} setLoading Whether to set loading('template-list')
		 */
		async getTemplates(setLoading = true) {
			const rootStore = useRootStore()
			if (setLoading) {
				rootStore.load('template-list')
			}
			try {
				const response = await api.getTemplates(this.context)
				this.templates = response.data.data
				this.templatesLoaded = true
			} finally {
				rootStore.done('template-list')
			}
		},

		/**
		 * Create a new template page
		 *
		 * @param {number} parentId ID of parent page for new template
		 */
		async createTemplate(parentId) {
			const rootStore = useRootStore()
			const template = {
				title: TEMPLATE_PAGE,
				parentId,
			}

			// We'll be done when the editor is loaded.
			rootStore.load('newTemplate')

			try {
				const response = await api.createTemplate(this.context, template)
				// Add new template to the beginning of templates array
				this.templates.unshift(response.data.data)

				return response.data.data.id
			} finally {
				rootStore.done('newTemplate')
			}

		},

		/**
		 * Rename a template
		 *
		 * @param {number} templateId ID of the template to rename
		 * @param {string} newTitle new title for the template
		 */
		async renameTemplate(templateId, newTitle) {
			const rootStore = useRootStore()
			rootStore.load(`templateRename-${templateId}`)
			try {
				const response = await api.renameTemplate(this.context, templateId, newTitle)
				this._updateTemplatePage(response.data.data)
			} finally {
				rootStore.done(`templateRename-${templateId}`)
			}
		},

		/**
		 * Set emoji for a template
		 *
		 * @param {object} template the template
		 * @param {number} template.templateId ID of the template
		 * @param {string} template.emoji emoji for the template
		 */
		async setTemplateEmoji({ templateId, emoji }) {
			const rootStore = useRootStore()
			rootStore.load(`templateEmoji-${templateId}`)
			try {
				const response = await api.setTemplateEmoji(this.context, templateId, emoji)
				this._updateTemplatePage(response.data.data)
			} finally {
				rootStore.done(`templateEmoji-${templateId}`)
			}
		},

		/**
		 * Delete the template with the given id
		 *
		 * @param {object} template the template
		 * @param {number} template.templateId ID of the template
		 */
		async deleteTemplate({ templateId }) {
			await api.deleteTemplate(this.context, templateId)
			this.templates.splice(this.templates.findIndex(p => p.id === templateId), 1)
		},
	},
})
