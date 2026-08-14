/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import {
	canonicalPathRoute,
	MAX_COMPARISON_ID_LENGTH,
	parseVersionComparisonRoute,
	resolveVersionComparisonRoute,
	withoutVersionComparisonRoute,
	withVersionComparisonRoute,
} from '../../util/versionComparisonRoute.js'

describe('version comparison routes', () => {
	it('distinguishes absent and valid pairs', () => {
		expect(parseVersionComparisonRoute({})).toEqual({ kind: 'absent' })
		expect(parseVersionComparisonRoute({ compareFrom: 'version:1710000000', compareTo: 'current:1720000000' }))
			.toEqual({ kind: 'valid', from: 'version:1710000000', to: 'current:1720000000' })
	})

	it.each([
		['current and historical', 'version:1710000000', 'current:1720000000'],
		['two historical versions', 'version:1710000000', 'version:1720000000'],
	])('round-trips %s', (_name, from, to) => {
		const location = withVersionComparisonRoute({ path: '/Atlas/Page', query: {}, hash: '' }, from, to)
		expect(parseVersionComparisonRoute(location.query))
			.toEqual({ kind: 'valid', from, to })
	})

	it.each([
		['one value', { compareFrom: '1' }],
		['empty value', { compareFrom: '', compareTo: 'current' }],
		['same value', { compareFrom: '1', compareTo: '1' }],
		['array value', { compareFrom: ['1', '2'], compareTo: 'current' }],
		['decoded slash', { compareFrom: 'versions/1', compareTo: 'current' }],
		['decoded backslash', { compareFrom: 'versions\\1', compareTo: 'current' }],
		['control character', { compareFrom: 'version\n1', compareTo: 'current' }],
		['overlong value', { compareFrom: 'x'.repeat(MAX_COMPARISON_ID_LENGTH + 1), compareTo: 'current' }],
	])('rejects %s', (_name, query) => {
		expect(parseVersionComparisonRoute(query)).toEqual({ kind: 'invalid' })
	})

	it('preserves unrelated query and hash while adding or removing a pair', () => {
		const route = {
			path: '/Atlas/Page',
			query: { fileId: '42', view: 'grid' },
			hash: '#rollout',
		}
		const compared = withVersionComparisonRoute(route, 'version:1710000000', 'current:1720000000')
		expect(compared).toEqual({
			path: '/Atlas/Page',
			query: {
				compareFrom: 'version:1710000000',
				compareTo: 'current:1720000000',
				fileId: '42',
				view: 'grid',
			},
			hash: '#rollout',
		})
		expect(withoutVersionComparisonRoute(compared)).toEqual(route)
		expect(JSON.stringify(compared)).not.toContain('/remote.php/dav')
	})

	it('canonicalizes only the slug path', () => {
		const route = {
			path: '/Atlas/Page',
			query: { compareFrom: 'version:1', compareTo: 'current:2', view: 'grid' },
			hash: '#rollout',
		}
		expect(canonicalPathRoute(route, '/Atlas-2/Page-42')).toEqual({
			path: '/Atlas-2/Page-42',
			query: route.query,
			hash: '#rollout',
		})
	})

	it('reports unknown IDs without substituting another version', () => {
		const current = { routeId: 'current:2', routeAliases: ['current'] }
		const old = { routeId: 'version:1' }
		expect(resolveVersionComparisonRoute([current, old], { from: 'missing', to: 'current' }))
			.toEqual({ first: null, second: current, missing: ['missing'] })
	})

	it('blocks one ambiguous identity without removing its safe alias', () => {
		const current = { routeId: 'current:2', routeAliases: ['current'] }
		const old = { routeId: 'version:1' }
		expect(resolveVersionComparisonRoute(
			[current, old],
			{ from: 'current:2', to: 'current' },
			['current:2'],
		)).toEqual({ first: null, second: current, missing: ['current:2'] })
	})
})
