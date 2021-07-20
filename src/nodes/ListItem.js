import { ListItem as TiptapListItem } from 'tiptap-extensions'

const TYPES = {
	BULLET: 0,
	CHECKBOX: 1,
}

export default class ListItem extends TiptapListItem {

	get defaultOptions() {
		return {
			nested: true,
		}
	}

	get schema() {
		return {
			attrs: {
				done: {
					default: false,
				},
				type: {
					default: TYPES.BULLET,
				},
			},
			draggable: false,
			content: 'paragraph block*',
			toDOM: node => {
				if (node.attrs.type === TYPES.BULLET) {
					return ['li', 0]
				}
				const listAttributes = { class: 'checkbox-item' }
				const checkboxAttributes = { type: 'checkbox', class: '', contenteditable: false }
				if (node.attrs.done) {
					checkboxAttributes.checked = true
					listAttributes.class += ' checked'
				}
				return [
					'li',
					listAttributes,
					[
						'input',
						checkboxAttributes,
					],
					[
						'label',
						0,
					],
				]
			},
			parseDOM: [
				{
					priority: 100,
					tag: 'li',
					getAttrs: el => {
						const checkbox = el.querySelector('input[type=checkbox]')
						return { done: checkbox && checkbox.checked, type: checkbox ? TYPES.CHECKBOX : TYPES.BULLET }
					},
				},
			],
			toMarkdown: (state, node) => {
				if (node.attrs.type === TYPES.CHECKBOX) {
					state.write(`[${node.attrs.done ? 'x' : ' '}] `)
				}
				state.renderContent(node)
			},
		}
	}

}
