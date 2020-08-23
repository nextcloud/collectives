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

// last grapheme in the collective name if it's a 2 byte utf8 char
function emoji(name) {
	const arr = [...name]
	const last = arr[arr.length - 1]
	if (last && last.length === 2) {
		return last
	}
	return null
}

// name without the emoji if there is one
function title(name) {
	if (emoji(name)) {
		return name.substring(0, name.length - 2).trim()
	}
	return name
}

export default function({ id, name, circleUniqueId }) {
	return {
		id,
		name,
		circleUniqueId,
		title: title(name),
		emoji: emoji(name),
	}
}
