import { BulletList as TiptapBulletList } from 'tiptap-extensions'

export default class BulletList extends TiptapBulletList {

	/* The bullet list input rules are handled in the ListItem node
	 * so we can make sure that "- [ ]" can still trigger todo list items
	 */
	inputRules() {
		return []
	}

}
