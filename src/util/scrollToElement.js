/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Scroll to page in page list
 *
 * @param {number} pageId ID of page to scroll
 */
export function scrollToPage(pageId) {
	const pageListItem = document.getElementById(`page-${pageId}`)
	scrollToElement(pageListItem)
}

/**
 * Scroll element into center if not visible
 *
 * @param {Element} element DOM element to scroll
 */
export default function scrollToElement(element) {
	if (!(element instanceof Element)) {
		console.error('Error: Not a DOM element', element)
		return
	}

	const elementRect = element.getBoundingClientRect()
	if (elementRect.bottom > window.innerHeight || elementRect.top < 0) {
		element.scrollIntoView({
			behavior: 'auto',
			block: 'center',
			inline: 'nearest',
		})
	}
}
