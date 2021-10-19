import { showWarning, showError } from '@nextcloud/dialogs'

/**
 * @param msg
 * @param details
 */
function content(msg, details) {
	return details
		? `<div>${msg}</div><div>${details}</div>`
		: msg
}

/**
 * @param details
 */
function options(details) {
	return {
		isHTML: !!details,
	}
}

/**
 * @param msg
 * @param e
 */
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
 *
 * @param {string} msg translation key for the error message
 * @return {Function} error handler function
 */
export default function(msg) {
	return e => showRequestException(t('collectives', msg), e)
}
