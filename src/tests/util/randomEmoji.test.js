/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from 'vitest'
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
