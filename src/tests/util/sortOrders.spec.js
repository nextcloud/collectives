/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { byOrder } from '../../util/sortOrders.js'

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
