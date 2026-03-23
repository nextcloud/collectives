/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type NcTextField from '@nextcloud/vue/components/NcTextField'

const ctrlFStack: NcTextField[] = []

/**
 * @param textField - the NcTextField component object
 */
function ctrlFHandler(textField: NcTextField) {
	if (!textField) {
		return false
	}

	// Pass through if unified search is open
	if (document.querySelector('[role="dialog"] .unified-search-modal')) {
		return false
	}

	// Pass through if already focussed
	if (textField.$el.contains(document.activeElement)) {
		return false
	}

	textField.focus()
	textField.select()
	return true
}

/**
 * Keydown event handler
 *
 * @param event - The keydown event
 */
function onKeyDown(event) {
	const isCtrlF = (event.ctrlKey || event.metaKey)
		&& !event.shiftKey
		&& !event.altKey
		&& event.key === 'f'

	if (!isCtrlF || ctrlFStack.length === 0) {
		return
	}

	const topVueEl = ctrlFStack[ctrlFStack.length - 1]
	const handled = ctrlFHandler(topVueEl)
	if (handled) {
		event.preventDefault()
		event.stopImmediatePropagation()
	}
}

window.addEventListener('keydown', onKeyDown, true)

/**
 * @param textField - the Vue element
 */
export function pushCtrlF(textField: VueElement) {
	ctrlFStack.push(textField)
}

/**
 * @param textField - the Vue element
 */
export function popCtrlF(textField: VueElement) {
	const index = ctrlFStack.lastIndexOf(textField)
	if (index !== -1) {
		ctrlFStack.splice(index, 1)
	}
}
