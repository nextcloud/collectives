/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import FullscreenIcon from 'vue-material-design-icons/ArrowExpandAll.vue'
import EmoticonIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import EyeIcon from 'vue-material-design-icons/Eye.vue'
import FormatListBulletedIcon from 'vue-material-design-icons/FormatListBulleted.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariantOutline.vue'
import StarOffIcon from 'vue-material-design-icons/StarOffOutline.vue'
import StarIcon from 'vue-material-design-icons/StarOutline.vue'
import TagMultipleIcon from 'vue-material-design-icons/TagMultiple.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'

/**
 * Command palette command definitions
 *
 * @param {object} context The context object
 */
export function useCommandPaletteCommands(context) {
	const {
		t,
		isPublic,
		currentCollective,
		currentPage,
		rootPage,
		isTextEdit,
		hasOutline,
		hasSubpages,
		isFavoritePage,
		currentCollectiveCanEdit,
		currentCollectiveCanShare,
		currentPageDavUrl,
		actions,
	} = context

	const addPageCommands = (commands) => {
		if (isTextEdit.value) {
			commands.push({
				id: 'command-view-mode',
				type: 'command',
				title: t('collectives', 'Switch to view mode'),
				subtitle: t('collectives', 'View the current page'),
				icon: EyeIcon,
				action: actions.setTextView,
			})
		} else {
			commands.push({
				id: 'command-edit-mode',
				type: 'command',
				title: t('collectives', 'Switch to edit mode'),
				subtitle: t('collectives', 'Edit the current page'),
				icon: PencilIcon,
				action: actions.setTextEdit,
			})
		}

		const outlineTitle = hasOutline.value(currentPage.value.id)
			? t('collectives', 'Hide outline')
			: t('collectives', 'Show outline')
		commands.push({
			id: 'command-toggle-outline',
			type: 'command',
			title: outlineTitle,
			subtitle: t('collectives', 'Toggle page outline visibility'),
			icon: FormatListBulletedIcon,
			action: () => actions.toggleOutline(currentPage.value.id),
		})

		const fullWidthTitle = currentPage.value.isFullWidth
			? t('collectives', 'Disable full width')
			: t('collectives', 'Enable full width')
		commands.push({
			id: 'command-full-width',
			type: 'command',
			title: fullWidthTitle,
			subtitle: t('collectives', 'Toggle full width view'),
			icon: FullscreenIcon,
			action: actions.toggleFullWidthAction,
		})

		if (currentPage.value.id !== rootPage.value?.id) {
			const isFavorite = isFavoritePage.value(currentCollective.value.id, currentPage.value.id)
			commands.push({
				id: 'command-favorite-page',
				type: 'command',
				title: isFavorite
					? t('collectives', 'Remove from favorites')
					: t('collectives', 'Add to favorites'),
				subtitle: t('collectives', 'Toggle page favorite status'),
				icon: isFavorite ? StarOffIcon : StarIcon,
				action: actions.toggleFavoriteAction,
			})
		}

		if (currentCollectiveCanShare.value && currentPage.value.id !== rootPage.value?.id) {
			commands.push({
				id: 'command-share-page',
				type: 'command',
				title: t('collectives', 'Share page'),
				subtitle: t('collectives', 'Open sharing options'),
				icon: ShareVariantIcon,
				action: actions.openShareTab,
			})
		}

		if (currentCollectiveCanEdit.value && currentPage.value.id !== rootPage.value?.id) {
			commands.push({
				id: 'command-page-emoji',
				type: 'command',
				title: t('collectives', 'Select page emoji'),
				subtitle: t('collectives', 'Choose an emoji for the page'),
				icon: EmoticonIcon,
				action: actions.gotoPageEmojiPicker,
			})
		}

		if (currentCollectiveCanEdit.value) {
			commands.push({
				id: 'command-manage-tags',
				type: 'command',
				title: t('collectives', 'Manage tags'),
				subtitle: t('collectives', 'Add or remove page tags'),
				icon: TagMultipleIcon,
				action: actions.openTagsModal,
			})
		}

		if (currentCollectiveCanEdit.value && currentPage.value.id !== rootPage.value?.id) {
			commands.push({
				id: 'command-move-copy',
				type: 'command',
				title: t('collectives', 'Move or copy page'),
				subtitle: t('collectives', 'Relocate or duplicate the page'),
				icon: OpenInNewIcon,
				action: actions.openMoveOrCopyModal,
			})
		}

		if (currentPageDavUrl.value) {
			commands.push({
				id: 'command-download',
				type: 'command',
				title: t('collectives', 'Download page'),
				subtitle: t('collectives', 'Download page as markdown'),
				icon: DownloadIcon,
				action: actions.downloadPage,
			})
		}

		if (currentCollectiveCanEdit.value && currentPage.value.id !== rootPage.value?.id) {
			const deleteTitle = hasSubpages.value(currentPage.value.id)
				? t('collectives', 'Delete page and subpages')
				: t('collectives', 'Delete page')
			commands.push({
				id: 'command-delete-page',
				type: 'command',
				title: deleteTitle,
				subtitle: t('collectives', 'Remove page permanently'),
				icon: DeleteIcon,
				action: actions.deleteCurrentPage,
			})
		}
	}

	const getCommands = (query) => {
		const commands = []

		if (!isPublic.value) {
			commands.push({
				id: 'command-new-collective',
				type: 'command',
				title: t('collectives', 'Create new collective'),
				subtitle: t('collectives', 'Start a new collective'),
				icon: PlusIcon,
				action: actions.createNewCollective,
			})
		}

		if (currentCollective.value && !isPublic.value) {
			commands.push({
				id: 'command-new-page',
				type: 'command',
				title: t('collectives', 'Create new page'),
				subtitle: t('collectives', 'Add a new page to current collective'),
				icon: PlusIcon,
				action: actions.createNewPage,
			})
		}

		if (currentCollective.value && currentPage.value) {
			addPageCommands(commands)
		}

		if (query) {
			return commands.filter((cmd) => cmd.title.toLowerCase().includes(query)
				|| cmd.subtitle?.toLowerCase().includes(query))
		}

		return commands
	}

	return {
		getCommands,
	}
}
