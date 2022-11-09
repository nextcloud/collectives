const byName = (a, b) => a.name.localeCompare(b.name, OC.getLanguage())
const byTitle = (a, b) => a.title.localeCompare(b.title, OC.getLanguage())
const byTimestamp = (a, b) => b.timestamp - a.timestamp

const byOrder = (a, b) => {
	if (a.index >= 0 && b.index >= 0) {
		// both are in the sort order - sort lower index first
		return a.index - b.index
	} else {
		// not in sort order (index = -1) -> put at the end sorted by title
		return b.index - a.index || byTitle(a, b)
	}
}

const pageOrders = {
	byOrder: 0,
	byTimestamp: 1,
	byTitle: 2,
}

// Invert key and value of pageOrders
const pageOrdersByNumber = Object.entries(pageOrders)
	.reduce((obj, [a, b]) => ({ ...obj, [b]: a }), {})

export {
	byName,
	byTitle,
	byTimestamp,
	byOrder,
	pageOrders,
	pageOrdersByNumber,
}
