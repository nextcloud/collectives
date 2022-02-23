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

const emojiSet = {}

// Manually chosen subset of emojis, meant to be friendly and non-offensive
emojiSet.face = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '☺️', '😚', '😙', '😋', '😛', '😜', '🤪', '😝', '🤗', '🤠', '🥳', '🥸', '😎', '🤓', '🤡', '👻', '👾', '🤖']
emojiSet.cat_face = ['😺', '😸', '😹', '😻', '😼']
emojiSet.emotion = ['💟', '❣️', '❤️', '🧡', '💛', '💚', '💙', '💜', '🤎', '🖤', '🤍', '💯', '💢', '💥', '💫', '💦', '💬', '🗯️', '💭', '💤']
emojiSet.hand = ['👍', '👍🏻', '👍🏼', '👍🏽', '👍🏾', '👍🏿']
emojiSet.body = ['👀']
emojiSet.animal = ['🐵', '🐒', '🦍', '🦧', '🐶', '🐕', '🦮', '🐩', '🐺', '🦊', '🦝', '🐱', '🐈', '🦁', '🐯', '🐅', '🐆', '🐴', '🐎', '🦄', '🦓', '🦌', '🦬', '🐮', '🐂', '🐃', '🐄', '🐷', '🐖', '🐗', '🐽', '🐏', '🐑', '🐐', '🐪', '🐫', '🦙', '🦒', '🐘', '🦣', '🦏', '🦛', '🐭', '🐁', '🐀', '🐹', '🐰', '🐇', '🐿️', '🦫', '🦔', '🦇', '🐻', '🐨', '🐼', '🦥', '🦦', '🦨', '🦘', '🦡', '🐾', '🦃', '🐔', '🐓', '🐣', '🐤', '🐥', '🐦', '🐧', '🕊️', '🦅', '🦆', '🦢', '🦉', '🦤', '🪶', '🦩', '🦚', '🦜', '🐸', '🐊', '🐢', '🦎', '🐍', '🐲', '🐉', '🦕', '🦖', '🐳', '🐋', '🐬', '🦭', '🐟', '🐠', '🐡', '🦈', '🐙', '🐚', '🐌', '🦋', '🐛', '🐜', '🐝', '🪲', '🐞', '🦗', '🦂', '🪱']
emojiSet.plant = ['💐', '🌸', '💮', '🏵️', '🌹', '🥀', '🌺', '🌻', '🌼', '🌷', '🌱', '🪴', '🌲', '🌳', '🌴', '🌵', '🌾', '🌿', '☘️', '☘', '🍀', '🍁', '🍂', '🍃']
emojiSet.food = ['🍇', '🍈', '🍉', '🍊', '🍋', '🍌', '🍍', '🥭', '🍎', '🍏', '🍐', '🍑', '🍒', '🍓', '🫐', '🥝', '🍅', '🫒', '🥥', '🥑', '🍆', '🥔', '🥕', '🌽', '🌶️', '🌶', '🫑', '🥒', '🥬', '🥦', '🧄', '🧅', '🍄', '🥜', '🌰', '🍦', '🍧', '🍨', '🍩', '🍪', '🎂', '🍰', '🧁', '🥧', '🍫', '🍬', '🍭', '🍮', '🍯']
emojiSet.drink = ['🍼', '🥛', '☕', '🫖', '🍵', '🍶', '🍾', '🍷', '🍸', '🍹', '🍺', '🍻', '🥂', '🥃', '🥤', '🧋', '🧃', '🧉', '🧊']
emojiSet.place = ['🌍', '🌎', '🌏', '🌐', '🧭', '🏔️', '⛰️', '🌋', '🗻', '🏕️', '🏖️', '🏜️', '🏝️', '🏞️', '⛲', '🌁', '🌃', '🏙️', '🌄', '🌅', '♨️', '🎠', '🎡', '🎢', '💈', '🎪']
emojiSet.transport = ['🚂', '🚃', '🚄', '🚅', '🚆', '🚇', '🚈', '🚉', '🚊', '🚝', '🚞', '🚋', '🚌', '🚍', '🚎', '🚐', '🚕', '🚖', '🚗', '🚘', '🚙', '🛻', '🚚', '🚛', '🚜', '🏍️', '🛵', '🦼', '🛺', '🚲', '🛴', '🛹', '🛼', '⚓', '⛵', '🛶', '🚤', '🛳️', '⛴️', '🛥️', '🚢', '✈️', '✈', '🛩️', '🛫', '🛬', '🪂', '💺', '🚁', '🚟', '🚠', '🚡', '🛰️', '🚀', '🛸']
emojiSet.sky = ['🌙', '🌚', '🌛', '🌜', '☀️', '🌝', '🌞', '🪐', '⭐', '🌟', '🌠', '☁️', '⛅', '🌀', '🌈', '🌂', '☂️', '☔', '⛱️', '⚡', '❄️', '🔥', '💧', '🌊']
emojiSet.event = ['✨', '🎈', '🎉', '🎊', '🎋', '🎍', '🎎', '🎏', '🎐', '🎀', '🎁']
emojiSet.sport = ['⚽', '⚾', '🥎', '🏀', '🏐', '🏈', '🏉', '🎾', '🥏', '🎳', '🏏', '🏑', '🏒', '🥍', '🏓', '🏸', '⛸️', '🤿', '🎽', '🎿', '🛷', '🥌']
emojiSet.game = ['🎯', '🪀', '🪁', '🎱', '🔮', '🪄', '🧿', '🪬', '🎮', '🎰', '🎲', '🧩', '🧸', '🪅', '🪆', '♠️', '♠', '♥️', '♥', '♦️', '♦', '♣️', '♣', '♟️', '♟', '🃏', '🀄']
emojiSet.arts = ['🎭', '🎨']
emojiSet.clothing = ['👛', '👜', '👑', '👒', '🎩', '🎓', '🧢', '💎']
emojiSet.sound = ['📢', '📣', '📯', '🔔']
emojiSet.musical_instrument = ['🎷', '🪗', '🎸', '🎹', '🎺', '🎻', '🪕', '🥁', '🪘']
emojiSet.light = ['🔍', '🔎', '🕯️', '💡', '🔦', '🪔']
emojiSet.book_paper = ['📔', '📕', '📖', '📗', '📘', '📙', '📚', '📓', '📒', '📃', '📜', '📄', '📰', '🗞️', '📑']
emojiSet.writing = ['✏️', '✏', '✒️', '✒', '🖋️', '🖋', '🖊️', '🖊', '🖌️', '🖌', '🖍️', '🖍', '📝']
emojiSet.office = ['💼', '📌', '📎', '✂️']

const defaultEmojis = Object.values(emojiSet).reduce((setA, setB) => setA.concat(setB))

/**
 * @param {Array} excludes list of emojis to exclude
 * @param {Array} emojis list of emojis to to pick from
 * @return {string} random emoji
 */
export default function randomEmoji(excludes = [], emojis = defaultEmojis) {
	let filteredEmojis = excludes.length ? emojis.filter(e => !excludes.includes(e)) : emojis
	if (filteredEmojis.length === 0) {
		// Fallback to full emoji set if filtered list is empty
		filteredEmojis = emojis
	}

	return filteredEmojis[Math.floor(Math.random() * filteredEmojis.length)]
}
