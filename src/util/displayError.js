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

function showRequestException(msg, e) {
	const details = e.response && e.response.data
	if (e.response && e.response.status < 500) {
		showWarning(content(msg, details), options(details))
	} else {
		console.error(e)
		showError(content(msg, details), options(details))
	}
}

/**
 * Error handler function to display a translation of the message
 * alongside the error itself.
 * @param {String} msg translation key for the error message
 * @returns {Function} error handler function
 */
export default function(msg) {
	return e => showRequestException(t('collectives', msg), e)
}
