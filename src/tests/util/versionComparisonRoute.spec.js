/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import {
	canonicalPathRoute,
	claimVersionComparisonRouteWarning,
	MAX_COMPARISON_ID_LENGTH,
	parseVersionComparisonRoute,
	rejectedVersionComparisonRoute,
	resolveVersionComparisonRoute,
	withoutVersionComparisonRoute,
	withVersionComparisonRoute,
} from '../../util/versionComparisonRoute.js'

const route = {
	path: '/Atlas/Page',
	query: { fileId: '42', view: 'grid' },
	hash: '#rollout',
}

describe('canonical version comparison routes', () => {
	it('warns once per rejected history entry and again after re-entry', () => {
		const query = { ...route.query, compareFrom: 'version:1', compareTo: 'current:2' }
		const rejected = rejectedVersionComparisonRoute({ ...route, query }, {})

		expect(claimVersionComparisonRouteWarning(query, {})).toBe(true)
		expect(claimVersionComparisonRouteWarning(query, rejected.state)).toBe(false)
		expect(claimVersionComparisonRouteWarning(query, {})).toBe(true)
		expect(rejected.query).toEqual(route.query)
	})

	it('R01 round-trips an exact ordered current/historical or historical/historical pair', () => {
		for (const [from, to] of [
			['version:1', 'current:2'],
			['version:1', 'version:2'],
		]) {
			const location = withVersionComparisonRoute(route, from, to)
			expect(parseVersionComparisonRoute(location.query)).toEqual({ kind: 'valid', from, to })
		}
	})

	it.each([
		['one value', { compareFrom: '1' }],
		['empty value', { compareFrom: '', compareTo: 'current' }],
		['identical value', { compareFrom: '1', compareTo: '1' }],
		['duplicate array', { compareFrom: ['1', '2'], compareTo: 'current' }],
		['slash', { compareFrom: 'versions/1', compareTo: 'current' }],
		['backslash', { compareFrom: 'versions\\1', compareTo: 'current' }],
		['control character', { compareFrom: 'version\n1', compareTo: 'current' }],
		['overlong identity', { compareFrom: 'x'.repeat(MAX_COMPARISON_ID_LENGTH + 1), compareTo: 'current' }],
	])('fails closed for %s without accepting an ambiguous route', (_name, query) => {
		expect(parseVersionComparisonRoute(query)).toEqual({ kind: 'invalid' })
	})

	it('distinguishes an absent route', () => {
		expect(parseVersionComparisonRoute(route.query)).toEqual({ kind: 'absent' })
	})

	it('R10 preserves unrelated query, hash, and path while closing a managed route', () => {
		const compared = withVersionComparisonRoute(route, 'version:1', 'current:2')
		expect(withoutVersionComparisonRoute(compared)).toEqual(route)
	})

	it('preserves the exact query pair while a renamed page path is canonicalized', () => {
		const compared = withVersionComparisonRoute(route, 'version:1', 'current:2')
		expect(canonicalPathRoute(compared, '/Atlas/Page-renamed')).toEqual({
			path: '/Atlas/Page-renamed',
			query: compared.query,
			hash: route.hash,
		})
	})

	it('R07 reports one missing version without substitution', () => {
		const options = [
			{ routeId: 'current:2', routeAliases: ['current'] },
			{ routeId: 'version:1' },
		]
		expect(resolveVersionComparisonRoute(options, { from: 'missing', to: 'current' }).missing)
			.toEqual(['missing'])
	})

	it('R08 reports two missing versions without substitution', () => {
		const options = [
			{ routeId: 'current:2', routeAliases: ['current'] },
			{ routeId: 'version:1' },
		]
		expect(resolveVersionComparisonRoute(options, { from: 'missing-1', to: 'missing-2' }).missing)
			.toEqual(['missing-1', 'missing-2'])
	})

	it.each([
		['canonical ID then alias', 'current:2', 'current'],
		['alias then canonical ID', 'current', 'current:2'],
	])('AUD-20 rejects %s resolving to the same semantic snapshot', (_name, from, to) => {
		const current = { key: 'current', routeId: 'current:2', routeAliases: ['current'] }
		const resolved = resolveVersionComparisonRoute(
			[current, { key: 'version:1', routeId: 'version:1' }],
			{ from, to },
		)

		expect(resolved).toEqual({
			first: current,
			second: current,
			missing: [],
			invalid: true,
		})
	})

	it('rejects rebuilt options with the same semantic key', () => {
		const canonical = { key: 'current', routeId: 'current:2' }
		const alias = { key: 'current', routeId: 'current' }

		expect(resolveVersionComparisonRoute(
			[canonical, alias],
			{ from: 'current:2', to: 'current' },
		)).toEqual({
			first: canonical,
			second: alias,
			missing: [],
			invalid: true,
		})
	})
})
