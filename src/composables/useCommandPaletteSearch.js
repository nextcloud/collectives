/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'
import PageIcon from '../components/Icon/PageIcon.vue'

/**
 * Command palette search and filtering logic
 *
 * @param {object} context The context object
 */
export function useCommandPaletteSearch(context) {
	const {
		t,
		isPublic,
		currentCollective,
		collectives,
		pages,
		rootPage,
		allPages,
		pagesForCollective,
		actions,
	} = context

	const getPages = (query) => {
		const normalizedQuery = query?.toLowerCase().trim()
		const currentCollectiveItems = []
		const otherCollectiveItems = []

		if (currentCollective.value) {
			const visiblePages = pages.value.filter((p) => p.id !== rootPage.value?.id)

			for (const page of visiblePages) {
				const title = page.title || page.fileName
				const matchesQuery = !normalizedQuery
					|| title.toLowerCase().includes(normalizedQuery)
					|| page.fileName?.toLowerCase().includes(normalizedQuery)
				if (matchesQuery) {
					currentCollectiveItems.push({
						id: `page-${page.id}`,
						type: 'page',
						title,
						subtitle: t('collectives', 'Page'),
						emoji: page.emoji,
						icon: page.emoji ? null : PageIcon,
						badge: null,
						action: () => actions.navigateToPage(page),
					})
				}
			}
		}

		if (!isPublic.value && allPages.value) {
			for (const collective of collectives.value) {
				if (collective.id === currentCollective.value?.id) {
					continue
				}

				const collectivePages = pagesForCollective.value(collective)
				if (!collectivePages || collectivePages.length === 0) {
					continue
				}

				const collectiveRootPage = collectivePages.find((p) => p.parentId === 0)

				for (const page of collectivePages) {
					if (page.id === collectiveRootPage?.id) {
						continue
					}

					const title = page.title || page.fileName
					const matchesQuery = !normalizedQuery
						|| title.toLowerCase().includes(normalizedQuery)
						|| page.fileName?.toLowerCase().includes(normalizedQuery)
						|| collective.name.toLowerCase().includes(normalizedQuery)
					if (matchesQuery) {
						otherCollectiveItems.push({
							id: `page-${collective.id}-${page.id}`,
							type: 'page-other',
							title,
							subtitle: collective.name,
							emoji: page.emoji,
							icon: page.emoji ? null : PageIcon,
							badge: collective.emoji || null,
							action: () => actions.navigateToPageInCollective(page, collective),
							collective,
							page,
						})
					}
				}
			}
		}

		return [...currentCollectiveItems, ...otherCollectiveItems]
	}

	const getCollectives = (query) => {
		const normalizedQuery = query?.toLowerCase().trim()
		const items = []

		for (const collective of collectives.value) {
			const matchesQuery = !normalizedQuery
				|| collective.name.toLowerCase().includes(normalizedQuery)

			if (matchesQuery) {
				items.push({
					id: `collective-${collective.id}`,
					type: 'collective',
					title: collective.name,
					subtitle: t('collectives', 'Collective'),
					emoji: collective.emoji,
					icon: collective.emoji ? null : CollectivesIcon,
					badge: null,
					action: () => actions.navigateToCollective(collective),
				})
			}
		}

		return items
	}

	return {
		getPages,
		getCollectives,
	}
}
