/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Remove from the collection the given item (identified by id).
 *
 * If the item is not found this is a noop.
 *
 * @param {object[]} collection array to remove item from.
 * @param {object} item the item
 * @param {number} item.id ID of the item to delete
 */
export function removeFrom(collection, item) {
	const index = collection.findIndex((i) => i.id === item.id)
	if (index > -1) {
		collection.splice(index, 1)
	}
}

/**
 * Update item in the collection or add if it does not exist
 *
 * The existing item is identified by the id.
 * If no item with a matching it exists the item will be added to the start of the collection.
 *
 * @param {object[]} collection array to modify
 * @param {object} item the item to update or add
 * @param {number} item.id used to find the item in the collection
 */
export function updateOrAddTo(collection, item) {
	const index = collection.findIndex((i) => i.id === item.id)
	if (index > -1) {
		collection.splice(index, 1, item)
	} else {
		collection.unshift(item)
	}
}
