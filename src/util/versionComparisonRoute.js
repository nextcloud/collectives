/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const COMPARE_FROM_QUERY = 'compareFrom'
export const COMPARE_TO_QUERY = 'compareTo'
export const COMPARISON_HISTORY_STATE = 'collectivesVersionComparison'
export const MAX_COMPARISON_ID_LENGTH = 255

/**
 * Replace only a route path during slug canonicalization.
 *
 * @param {object} route Current route
 * @param {string} path Canonical path
 * @return {object} Vue Router location
 */
export function canonicalPathRoute(route, path) {
	return {
		path,
		query: route.query,
		hash: route.hash,
	}
}

/**
 * Parse the two comparison-only parameters without accepting Vue Router's
 * array representation for duplicate values.
 *
 * @param {object} query Decoded Vue Router query
 * @return {{kind: 'absent'}|{kind: 'invalid'}|{kind: 'valid', from: string, to: string}}
 */
export function parseVersionComparisonRoute(query) {
	const hasFrom = Object.hasOwn(query, COMPARE_FROM_QUERY)
	const hasTo = Object.hasOwn(query, COMPARE_TO_QUERY)
	if (!hasFrom && !hasTo) {
		return { kind: 'absent' }
	}
	if (!hasFrom || !hasTo) {
		return { kind: 'invalid' }
	}

	const from = query[COMPARE_FROM_QUERY]
	const to = query[COMPARE_TO_QUERY]
	if (!isValidComparisonId(from)
		|| !isValidComparisonId(to)
		|| from === to) {
		return { kind: 'invalid' }
	}

	return { kind: 'valid', from, to }
}

/**
 * Build a route location containing one comparison pair.
 *
 * @param {object} route Current route
 * @param {string} from Earlier/current opaque identity
 * @param {string} to Later/current opaque identity
 * @return {object} Vue Router location
 */
export function withVersionComparisonRoute(route, from, to) {
	return {
		path: route.path,
		query: {
			...route.query,
			[COMPARE_FROM_QUERY]: from,
			[COMPARE_TO_QUERY]: to,
		},
		hash: route.hash,
	}
}

/**
 * Remove only comparison parameters.
 *
 * @param {object} route Current route
 * @return {object} Vue Router location
 */
export function withoutVersionComparisonRoute(route) {
	const query = { ...route.query }
	delete query[COMPARE_FROM_QUERY]
	delete query[COMPARE_TO_QUERY]
	return {
		path: route.path,
		query,
		hash: route.hash,
	}
}

/**
 * Resolve a URL identity against selector options without substituting.
 *
 * @param {object[]} options Comparison options
 * @param {{from: string, to: string}} requested Requested pair
 * @param {Iterable<string>} unavailableRouteIds Ambiguous identities to reject
 * @return {{first: object|null, second: object|null, missing: string[], invalid?: 'self-pair'}}
 */
export function resolveVersionComparisonRoute(options, requested, unavailableRouteIds = []) {
	const unavailable = new Set(unavailableRouteIds)
	const byRouteId = new Map()
	for (const option of options) {
		for (const routeId of [option.routeId, ...(option.routeAliases ?? [])]) {
			if (!unavailable.has(routeId)) {
				byRouteId.set(routeId, option)
			}
		}
	}
	const first = byRouteId.get(requested.from) ?? null
	const second = byRouteId.get(requested.to) ?? null
	const missing = [
		...first ? [] : [requested.from],
		...second ? [] : [requested.to],
	]
	return {
		first,
		second,
		missing,
		...(first && second
			&& (first === second || (first.key !== undefined && first.key === second.key))
			? { invalid: 'self-pair' }
			: {}),
	}
}

/**
 * Test an already-decoded opaque version identity.
 *
 * @param {unknown} value Candidate identity
 * @return {value is string} Whether it is a bounded route identity
 */
export function isValidComparisonId(value) {
	return typeof value === 'string'
		&& value.length > 0
		&& value.length <= MAX_COMPARISON_ID_LENGTH
		&& !value.includes('/')
		&& !value.includes('\\')
		&& ![...value].some((character) => {
			const codePoint = character.codePointAt(0)
			return codePoint <= 31 || codePoint === 127
		})
}
