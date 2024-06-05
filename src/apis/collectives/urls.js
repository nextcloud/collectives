import { generateUrl, generateOcsUrl } from '@nextcloud/router'

/**
 * Url for the versioned collectives api
 *
 * @param {string} version - Version of the api - currently `v1.0`
 * @param {...any} parts - url parts to append - will be joined with `/`
 */
export function apiUrl(version, ...parts) {
	const path = ['apps/collectives/api', version, ...parts]
		.join('/')
	return generateOcsUrl(path)
}

/**
 * Url for the collectives app endpoints
 *
 * @param {...any} parts - url parts to append - will be joined with `/`
 */
export function collectivesUrl(...parts) {
	const path = ['apps/collectives/_api', ...parts]
		.join('/')
	return generateUrl(path)
}

/**
 * Url for pages paths inside the given context.
 *
 * @param {object} context - either the current collective or a share context
 * @param {...any} parts - url parts to append.
 */
export function pagesUrl(context, ...parts) {
	return context.isPublic
		? collectivesUrl('p', context.shareTokenParam, '_pages', ...parts)
		: collectivesUrl(context.collectiveId, '_pages', ...parts)
}

/**
 *
 * @param {number} collectiveId collectiveId to search in
 */
export function contentSearchUrl(collectiveId) {
	return collectivesUrl(collectiveId, '_pages/search')
}
