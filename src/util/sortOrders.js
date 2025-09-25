/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const byName = (a, b) => a.name.localeCompare(b.name, OC.getLanguage(), { numeric: true })
const byTitleAsc = (a, b) => a.title.localeCompare(b.title, OC.getLanguage(), { numeric: true })
const byTitleDesc = (a, b) => byTitleAsc(b, a)
const byTimeAsc = (a, b) => b.timestamp - a.timestamp
const byTimeDesc = (a, b) => byTimeAsc(b, a)

/**
 *
 * @param {object} a first sortable object
 * @param {object} b second sortable object
 */
function byOrder(a, b) {
	if (a.index >= 0 && b.index >= 0) {
		// both are in the sort order - sort lower index first
		return a.index - b.index
	} else {
		// not in sort order (index = -1) -> put at the end sorted ascending by title
		return b.index - a.index || byTitleAsc(a, b)
	}
}

const pageOrders = {
	byOrder: 0,
	byTimeAsc: 1,
	byTitleAsc: 2,
	byTimeDesc: 3,
	byTitleDesc: 4,
}

// Invert key and value of pageOrders
const pageOrdersByNumber = Object.entries(pageOrders)
	.reduce((obj, [a, b]) => ({ ...obj, [b]: a }), {})

export {
	byName,
	byOrder,
	byTimeAsc,
	byTimeDesc,
	byTitleAsc,
	byTitleDesc,
	pageOrders,
	pageOrdersByNumber,
}
