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

import randomEmoji from '../../util/randomEmoji.js'

test('returns an emoji', () => {
	const emojis = ['🥳']
	const emoji = randomEmoji([], emojis)
	expect(emoji).toBe('🥳')
})

test('takes excludes into account', () => {
	const excludes = ['🥳', '🥸']
	const emojis = ['🥳', '🥸', '🥰']
	for (let i = 0; i < 100; i += 1) {
		const emoji = randomEmoji(excludes, emojis)
		expect(emoji).toBe('🥰')
	}
})

test('returns an emoji if all are excluded', () => {
	const excludes = ['🥳', '🥸', '🥰']
	const emojis = ['🥳', '🥸', '🥰']
	const emoji = randomEmoji(excludes, emojis)
	expect(emojis).toContain(emoji)
})
