/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from 'vitest'
import { byName, byOrder, byTimeAsc, byTimeDesc, byTitleAsc, byTitleDesc } from '../../util/sortOrders.js'

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
	const sortedAsc = [
		{ title: '1' },
		{ title: '2' },
		{ title: '10' },
		{ title: 'a' },
	]
	const sortedDesc = [
		{ title: 'a' },
		{ title: '10' },
		{ title: '2' },
		{ title: '1' },
	]
	expect(unsorted.sort(byTitleAsc))
		.toStrictEqual(sortedAsc)
	expect(unsorted.sort(byTitleDesc))
		.toStrictEqual(sortedDesc)
})

test('by time', () => {
	const unsorted = [
		{ timestamp: 7 },
		{ timestamp: 1 },
		{ timestamp: 3 },
		{ timestamp: 10 },
	]
	const sortedAsc = [
		{ timestamp: 10 },
		{ timestamp: 7 },
		{ timestamp: 3 },
		{ timestamp: 1 },
	]
	const sortedDesc = [
		{ timestamp: 1 },
		{ timestamp: 3 },
		{ timestamp: 7 },
		{ timestamp: 10 },
	]
	expect(unsorted.sort(byTimeAsc))
		.toStrictEqual(sortedAsc)
	expect(unsorted.sort(byTimeDesc))
		.toStrictEqual(sortedDesc)
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
