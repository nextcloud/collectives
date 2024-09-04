/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { byName, byTitle, byOrder } from '../../util/sortOrders.js'

global.OC = {
	getLanguage: () => 'en',
}

test('by name', () => {
	const unsorted = [
		{ name: '2' },
		{ name: 'a' },
		{ name: '10' },
		{ name: '1' },
	]
	const sorted = [
		{ name: '1' },
		{ name: '2' },
		{ name: '10' },
		{ name: 'a' },
	]
	expect(unsorted.sort(byName))
		.toStrictEqual(sorted)
})

test('by title', () => {
	const unsorted = [
		{ title: '2' },
		{ title: 'a' },
		{ title: '10' },
		{ title: '1' },
	]
	const sorted = [
		{ title: '1' },
		{ title: '2' },
		{ title: '10' },
		{ title: 'a' },
	]
	expect(unsorted.sort(byTitle))
		.toStrictEqual(sorted)
})

test('by indices', () => {
	const one = { index: 0 }
	const two = { index: 1 }
	const three = { index: 2 }
	expect([three, one, two].sort(byOrder))
		.toStrictEqual([one, two, three])
})

test('missing indices', () => {
	global.OC = { getLanguage: () => 'en' }
	const one = { index: 0 }
	const two = { index: 1 }
	const other = { index: -1, title: 'asdf' }
	const other2 = { index: -1, title: 'sdf' }
	expect([other, other2, one, two].sort(byOrder))
		.toStrictEqual([one, two, other, other2])
})
