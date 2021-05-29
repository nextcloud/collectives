const byName = (a, b) => a.name.localeCompare(b.name, OC.getLanguage())
const byTitle = (a, b) => a.title.localeCompare(b.title, OC.getLanguage())
const byTimestamp = (a, b) => b.timestamp - a.timestamp

export {
	byName,
	byTitle,
	byTimestamp,
}
