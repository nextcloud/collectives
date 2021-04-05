import { showWarning, showError } from '@nextcloud/dialogs'

function content(msg, details) {
	return details
		? `<div>${msg}</div><div>${details}</div>`
		: msg
}

function options(details) {
	return {
		isHTML: !!details,
	}
}

export default function(msg, e) {
	const details = e.response && e.response.data
	if (e.response && e.response.status < 500) {
		showWarning(content(msg, details), options(details))
	} else {
		console.error(e)
		showError(content(msg, details), options(details))
	}
}
