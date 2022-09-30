/*
 * @copyright Copyright (c) 2022 Jonas <jonas@freesources.org>
 *
 * @author Jonas <jonas@freesources.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
