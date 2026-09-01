/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const COMPARE_FROM_QUERY = 'compareFrom'
export const COMPARE_TO_QUERY = 'compareTo'
export const COMPARISON_HISTORY_STATE = 'collectivesVersionComparison'
export const COMPARISON_WARNING_STATE = 'collectivesVersionComparisonWarning'
export const MAX_COMPARISON_ID_LENGTH = 255

/* eslint-disable jsdoc/require-jsdoc */

const warningKey = (query) => JSON.stringify([query[COMPARE_FROM_QUERY], query[COMPARE_TO_QUERY]])
const routeLocation = (route, query = route.query) => ({ path: route.path, query, hash: route.hash })
function invalidComparisonIdCharacter(character) {
	const codePoint = character.codePointAt(0)
	return character === '/' || character === '\\' || codePoint <= 31 || codePoint === 127
}

export function claimVersionComparisonRouteWarning(query, state = window.history.state) {
	return state?.[COMPARISON_WARNING_STATE] !== warningKey(query)
}

export function rejectedVersionComparisonRoute(route) {
	return {
		...withoutVersionComparisonRoute(route),
		state: {
			[COMPARISON_WARNING_STATE]: warningKey(route.query),
		},
	}
}

export function canonicalPathRoute(route, path) {
	return routeLocation({ ...route, path })
}

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

export function withVersionComparisonRoute(route, from, to) {
	return routeLocation(route, {
		...route.query,
		[COMPARE_FROM_QUERY]: from,
		[COMPARE_TO_QUERY]: to,
	})
}

export function withoutVersionComparisonRoute(route) {
	const query = { ...route.query }
	delete query[COMPARE_FROM_QUERY]
	delete query[COMPARE_TO_QUERY]
	return routeLocation(route, query)
}

export function resolveVersionComparisonRoute(options, requested) {
	const byRouteId = new Map(options.flatMap((option) => [option.routeId, ...(option.routeAliases ?? [])].map((routeId) => [routeId, option])))
	const first = byRouteId.get(requested.from) ?? null
	const second = byRouteId.get(requested.to) ?? null
	const missing = [requested.from, requested.to].filter((routeId) => !byRouteId.has(routeId))
	return { first, second, missing, invalid: Boolean(first && second && (first === second || (first.key !== undefined && first.key === second.key))) }
}

export function isValidComparisonId(value) {
	return typeof value === 'string'
		&& value.length > 0
		&& value.length <= MAX_COMPARISON_ID_LENGTH
		&& ![...value].some(invalidComparisonIdCharacter)
}
