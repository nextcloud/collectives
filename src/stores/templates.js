/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'
import { useRootStore } from './root.js'
import { useCollectivesStore } from './collectives.js'
import { usePagesStore } from './pages.js'
import * as api from '../apis/collectives/index.js'
import { byTitle } from '../util/sortOrders.js'
import { TEMPLATE_PAGE } from '../constants.js'

export const useTemplatesStore = defineStore('templates', {
	state: () => ({
		allTemplates: {},
		allTemplatesLoaded: {},
	}),

	getters: {
		collectiveId() {
			const collectivesStore = useCollectivesStore()
			return collectivesStore.templatesCollectiveId || collectivesStore.currentCollective.id
		},

		context() {
			const rootStore = useRootStore()
			return {
				isPublic: rootStore.isPublic,
				collectiveId: this.collectiveId,
				shareTokenParam: rootStore.shareTokenParam,
			}
		},

		templates: (state) => {
			return state.allTemplates[state.collectiveId] || []
		},

		templatesLoaded: (state) => {
			return state.allTemplatesLoaded[state.collectiveId] || false
		},

		sortedTemplates() {
			return this.templates.sort(byTitle)
		},

		hasTemplates() {
			return this.templates.length > 0
		},

		hasSubpages() {
			return (templateId) => {
				return this.templates.filter(p => p.parentId === templateId).length > 0
			}
		},

		rootTemplateId() {
			return this.hasTemplates
				? this.templates[0].parentId
				: 0
		},

		rootTemplates() {
			return this.sortedTemplates
				.filter(template => template.parentId === this.rootTemplateId)
		},

		templateFilePath: () => (template) => {
			const pagesStore = usePagesStore()
			return pagesStore.pageFilePath(template)
		},
	},

	actions: {
		_updateTemplatePage(template) {
			if (this.allTemplates[this.collectiveId] === undefined) {
				set(this.allTemplates, this.collectiveId, [])
			}
			this.allTemplates[this.collectiveId].splice(
				this.allTemplates[this.collectiveId].findIndex(p => p.id === template.id),
				1,
				template,
			)
		},

		/**
		 * Get list of all templates for current collective
		 *
		 * @param {boolean} setLoading Whether to set template list as loading
		 */
		async getTemplates(setLoading = true) {
			const rootStore = useRootStore()
			const collectiveId = this.collectiveId
			if (setLoading) {
				rootStore.load(`template-list-${collectiveId}`)
			}
			try {
				const response = await api.getTemplates(this.context)
				set(this.allTemplates, collectiveId, response.data.data)
				set(this.allTemplatesLoaded, collectiveId, true)
			} finally {
				rootStore.done(`template-list-${collectiveId}`)
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
				this.allTemplates[this.collectiveId]?.unshift(response.data.data)

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
			this.allTemplates[this.collectiveId]?.splice(this.allTemplates[this.collectiveId]?.findIndex(p => p.id === templateId), 1)
		},
	},
})
