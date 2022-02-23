const byName = (a, b) => a.name.localeCompare(b.name, OC.getLanguage())
const byTitle = (a, b) => a.title.localeCompare(b.title, OC.getLanguage())
const byTimestamp = (a, b) => b.timestamp - a.timestamp

const pageOrders = {
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
	pageOrders,
	pageOrdersByNumber,
}
