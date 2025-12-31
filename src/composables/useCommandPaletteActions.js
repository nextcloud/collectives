/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

/**
 * Command palette action handlers
 *
 * @param {object} context The context object
 */
export function useCommandPaletteActions(context) {
	const {
		router,
		currentCollective,
		currentPage,
		currentPageDavUrl,
		rootPage,
		pagePath,
		collectivePath,
		setTextEdit,
		setTextView,
		toggleOutline,
		setNewPageParentId,
		setFullWidthView,
		trashPage,
		toggleFavoritePage,
		show,
		setActiveSidebarTab,
	} = context

	const navigateToPage = (page) => {
		const path = pagePath(page)
		router.push(path)
	}

	const buildPagePath = (page, collective) => {
		const collectiveBasePath = collectivePath(collective)

		if (!page || page.parentId === 0) {
			return collectiveBasePath
		}

		if (!page.slug) {
			const { filePath, fileName, title, id } = page
			const titlePart = fileName !== 'Readme.md' && title

			const pagePath = [...filePath.split('/'), titlePart]
				.filter(Boolean).map(encodeURIComponent).join('/')
			return pagePath
				? `${collectiveBasePath}/${pagePath}?fileId=${id}`
				: collectiveBasePath
		}

		return `${collectiveBasePath}/${page.slug}-${page.id}`
	}

	const navigateToPageInCollective = (page, collective) => {
		const pagePath = buildPagePath(page, collective)
		router.push(pagePath)
	}

	const navigateToCollective = (collective) => {
		const path = collectivePath(collective)
		router.push(path)
	}

	const createNewPage = () => {
		const parentId = currentPage.value && currentPage.value.id !== rootPage.value?.id
			? currentPage.value.parentId
			: (rootPage.value?.id || 0)
		setNewPageParentId(parentId)
	}

	const createNewCollective = () => {
		emit('open-new-collective-modal')
	}

	const toggleFullWidthAction = () => {
		setFullWidthView({
			pageId: currentPage.value.id,
			fullWidthView: !currentPage.value.isFullWidth,
		})
	}

	const toggleFavoriteAction = () => {
		toggleFavoritePage({
			id: currentCollective.value.id,
			pageId: currentPage.value.id,
		})
	}

	const openShareTab = () => {
		show('sidebar')
		setActiveSidebarTab('sharing')
	}

	const gotoPageEmojiPicker = () => {
		emit('collectives:page:open-emoji-picker')
	}

	const openTagsModal = () => {
		emit('collectives:page:open-tags-modal')
	}

	const openMoveOrCopyModal = () => {
		emit('collectives:page:open-move-or-copy-modal')
	}

	const downloadPage = () => {
		const link = document.createElement('a')
		link.href = currentPageDavUrl.value
		link.download = currentPage.value.fileName
		link.click()
	}

	const deleteCurrentPage = async () => {
		const pageId = currentPage.value.id
		const currentPageId = currentPage.value?.id

		try {
			await trashPage({ pageId })
		} catch (e) {
			console.error(e)
			showError(window.t('collectives', 'Could not delete the page'))
			return
		}

		if (currentPageId === pageId) {
			router.push(`/${encodeURIComponent(currentCollective.value.name)}`)
		}

		emit('collectives:page-list:page-trashed')
		showSuccess(window.t('collectives', 'Page deleted'))
	}

	return {
		navigateToPage,
		navigateToPageInCollective,
		navigateToCollective,
		createNewPage,
		createNewCollective,
		setTextEdit,
		setTextView,
		toggleOutline,
		toggleFullWidthAction,
		toggleFavoriteAction,
		openShareTab,
		gotoPageEmojiPicker,
		openTagsModal,
		openMoveOrCopyModal,
		downloadPage,
		deleteCurrentPage,
	}
}
