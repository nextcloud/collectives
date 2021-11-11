import { showWarning, showError } from '@nextcloud/dialogs'
import escapeHtml from 'escape-html'

/**
 * @param {string} msg the error message
 * @param {string} details details to be provided with the error
 */
function content(msg, details) {
	return details
		? `<div>${escapeHtml(msg)}</div><div>${escapeHtml(details)}</div>`
		: msg
}

/**
 * @param {string} details details to be provided with the error
 */
function options(details) {
	return {
		isHTML: !!details,
	}
}

/**
 * @param {string} msg the error message
 * @param {Error} e request exception from axios
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
