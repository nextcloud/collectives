/**
 * @copyright Copyright (c) 2020 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

// There are two ways of modifying emojis:
// * skin color
// * variation selector
// Use this in regular expressions to match the full emoji
// including possible modifications.
const flexibleEmoji = '\\p{Emoji}\\p{Emoji_Modifier}?\\p{Variation_Selector}?'

// Multiple Emojis can be joined together to form a new one.
// This regexp will match all joined emojis at the end of the string.
const trailingEmojiRegexp = new RegExp(
	`${flexibleEmoji}(‍${flexibleEmoji})*$`, 'u'
)

function emoji(name) {
	const match = name.match(trailingEmojiRegexp)
	return match && !match[0].match(/\d/) ? match[0] : ''
}

// name without the emoji if there is one
function title(name) {
	return name.replace(new RegExp(`${emoji(name)}$`), '').trim()
}

export default function({ id, name, circleUniqueId, admin }) {
	return {
		id,
		name,
		circleUniqueId,
		admin,
		title: title(name),
		emoji: emoji(name),
	}
}
