/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

/**
 * Translate the reason why publishing a static site failed
 *
 * @param {?string} lastError error code of the static site as stored by the backend
 * @return {string} translated message, a generic one for unknown codes
 */
export function staticSiteErrorMessage(lastError) {
	switch (lastError) {
		case 'generator_unavailable':
			return t('collectives', 'The publish service is currently not available. Please try again later.')
		case 'generator_rejected':
			return t('collectives', 'The publish service could not process the request. Please contact your administrator.')
		case 'generator_failed':
			return t('collectives', 'The publish service could not build the website. Please contact your administrator.')
		default:
			return t('collectives', 'Publishing the website failed.')
	}
}
