/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useLocalStorage } from '@vueuse/core'
import { defineStore } from 'pinia'
import { set } from 'vue'
import * as api from '../apis/collectives/index.js'
import { INDEX_PAGE, PAGE_SUFFIX, TEMPLATE_PAGE, TEMPLATE_PATH } from '../constants.js'
import { byTitleAsc } from '../util/sortOrders.js'
import { removeFrom, updateOrAddTo } from './collectionHelpers.js'
import { useCollectivesStore } from './collectives.js'
import { usePagesStore } from './pages.js'
import { useRootStore } from './root.js'

const STORE_PREFIX = 'collectives/pinia/templates/'

export const useTemplatesStore = defineStore('templates', {
	state: () => ({
		allTemplates: useLocalStorage(STORE_PREFIX + 'allTemplates', {}),
		allTemplatesLoaded: useLocalStorage(STORE_PREFIX + 'allTemplatesLoaded', {}),
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
			return this.templates.sort(byTitleAsc)
		},

		hasTemplates() {
			return this.templates.length > 0
		},

		hasSubpages() {
			return (templateId) => {
				return this.templates.filter((p) => p.parentId === templateId).length > 0
			}
		},

		rootTemplateId() {
			return this.hasTemplates
				? this.templates[0].parentId
				: 0
		},

		rootTemplates() {
			return this.sortedTemplates
				.filter((template) => template.parentId === this.rootTemplateId)
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
			updateOrAddTo(this.allTemplates[this.collectiveId], template)
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
				set(this.allTemplates, collectiveId, response.data.ocs.data.templates)
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
				const created = response.data.ocs.data.template
				updateOrAddTo(this.allTemplates[this.collectiveId], created)

				return response.data.ocs.data.template.id
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
				this._updateTemplatePage(response.data.ocs.data.template)
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
				this._updateTemplatePage(response.data.ocs.data.template)
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
			if (this.allTemplates[this.collectiveId]) {
				removeFrom(this.allTemplates[this.collectiveId], { id: templateId })
			}
		},

		/**
		 * Update all pages provided if they are templates.
		 *
		 * Remove those listed as removed
		 *
		 * @param {number} collectiveId ID of the collective to work on
		 * @param {object} changes the page
		 * @param {object[]} changes.pages updated records for pages
		 * @param {number[]} changes.removed ids of all pages that were removed entirely
		 */
		updateTemplates(collectiveId, { pages, removed }) {
			if (collectiveId !== this.collectiveId) {
				// only handle changes to the current collective
				return
			}
			for (const page of (pages || [])) {
				if (page.filePath !== TEMPLATE_PATH && !page.filePath.startsWith(TEMPLATE_PATH + '/')) {
					// Only handle templates here.
					continue
				}
				if (page.filePath === TEMPLATE_PATH && page.fileName === INDEX_PAGE + PAGE_SUFFIX) {
					// Ignore the Readme.md in the template path
					continue
				}
				updateOrAddTo(this.allTemplates[collectiveId], page)
			}
			if (this.allTemplates[this.collectiveId]) {
				for (const id of (removed || [])) {
					removeFrom(this.allTemplates[collectiveId], { id })
				}
			}
		},

	},
})
