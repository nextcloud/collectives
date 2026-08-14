/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { isValidComparisonId } from './versionComparisonRoute.js'

export const CURRENT_VERSION_KEY = 'current'

const CURRENT_ROUTE_NAMESPACE = 'current'
const HISTORICAL_ROUTE_NAMESPACE = 'version'

/**
 * Select the supported renderer without using the Text API version as a gate.
 *
 * @param {object} options Options
 * @param {function(object): Promise<object>|undefined} options.comparisonFactory Semantic comparison factory
 * @param {function(object, object): void|undefined} options.viewerCompare Viewer comparison function
 * @param {boolean} options.isMobile Whether the device is mobile
 * @return {'semantic'|'viewer'|'unsupported'} Renderer
 */
export function selectVersionComparisonRenderer({ comparisonFactory, viewerCompare, isMobile }) {
	if (typeof comparisonFactory === 'function') {
		return 'semantic'
	}
	if (!isMobile && typeof viewerCompare === 'function') {
		return 'viewer'
	}
	return 'unsupported'
}

/**
 * Build local selector options and quarantine unusable historical identities.
 *
 * @param {object} currentVersion Persisted current version
 * @param {object[]} versions Historical version list
 * @return {{ambiguousRouteIds: string[], options: object[], quarantined: object[]}} Selector state
 */
export function createVersionComparisonState(currentVersion, versions) {
	const currentSnapshot = versions.find(({ isCurrentSnapshot }) => isCurrentSnapshot)
		?? versions.find(({ mtime }) => mtime === currentVersion.mtime)
	const currentMtime = currentSnapshot?.mtime ?? currentVersion.mtime
	const currentSnapshotId = isValidHistoricalIdentity(currentSnapshot?.fileVersion)
		? currentSnapshot.fileVersion
		: String(Math.floor(currentMtime / 1000))
	const currentRouteId = namespacedRouteId(CURRENT_ROUTE_NAMESPACE, currentSnapshotId)
	const current = {
		fileInfo: currentVersion,
		fileVersion: null,
		key: CURRENT_VERSION_KEY,
		kind: 'current',
		label: currentVersion.label,
		mtime: currentMtime,
		routeAliases: [CURRENT_VERSION_KEY],
		routeId: currentRouteId,
		url: currentVersion.source ?? currentVersion.url,
	}
	const candidates = versions.filter((version) => version !== currentSnapshot)
	const identityCounts = candidates.reduce((counts, version) => {
		const fileVersion = version.fileVersion
		if (isValidHistoricalIdentity(fileVersion)) {
			counts.set(fileVersion, (counts.get(fileVersion) ?? 0) + 1)
		}
		return counts
	}, new Map())
	const duplicateIdentities = new Set([...identityCounts]
		.filter(([, count]) => count > 1)
		.map(([fileVersion]) => fileVersion))
	const quarantined = []
	const historical = candidates
		.flatMap((version) => {
			const fileVersion = version.fileVersion
			const reason = !isValidHistoricalIdentity(fileVersion)
				? 'invalid'
				: duplicateIdentities.has(fileVersion)
					? 'duplicate'
					: null
			if (reason) {
				quarantined.push({ fileVersion, reason })
				return []
			}
			const routeId = namespacedRouteId(HISTORICAL_ROUTE_NAMESPACE, fileVersion)
			const previousCurrentRouteId = namespacedRouteId(CURRENT_ROUTE_NAMESPACE, fileVersion)
			return [{
				fileInfo: version,
				fileVersion,
				key: routeId,
				kind: 'historical',
				label: version.label,
				mtime: version.mtime,
				routeAliases: previousCurrentRouteId === currentRouteId ? [] : [previousCurrentRouteId],
				routeId,
				url: version.source ?? version.url,
			}]
		})
		.toSorted(compareSnapshots)

	return {
		ambiguousRouteIds: [...duplicateIdentities]
			.flatMap((fileVersion) => [
				namespacedRouteId(HISTORICAL_ROUTE_NAMESPACE, fileVersion),
				namespacedRouteId(CURRENT_ROUTE_NAMESPACE, fileVersion),
			])
			.filter((routeId) => routeId !== currentRouteId),
		options: [current, ...historical.reverse()],
		quarantined,
	}
}

/**
 * Build one route identity without mixing current and persisted namespaces.
 *
 * @param {string} namespace Identity namespace
 * @param {string} identity Snapshot identity
 * @return {string} Namespaced route identity
 */
function namespacedRouteId(namespace, identity) {
	return `${namespace}:${identity}`
}

/**
 * Validate both a DAV version basename and every route identity derived from it.
 *
 * @param {unknown} fileVersion DAV version basename
 * @return {fileVersion is string} Whether the identity is safe for routing
 */
function isValidHistoricalIdentity(fileVersion) {
	return isValidComparisonId(fileVersion)
		&& isValidComparisonId(namespacedRouteId(HISTORICAL_ROUTE_NAMESPACE, fileVersion))
		&& isValidComparisonId(namespacedRouteId(CURRENT_ROUTE_NAMESPACE, fileVersion))
}

/**
 * Return a pair in chronological Earlier/After order.
 *
 * @param {object} first First selection
 * @param {object} second Second selection
 * @return {{earlier: object, later: object}} Normalized pair
 */
export function normalizeVersionComparisonPair(first, second) {
	if (!first || !second || first.key === second.key) {
		throw new TypeError('Two distinct versions are required')
	}
	return compareSnapshots(first, second) <= 0
		? { earlier: first, later: second }
		: { earlier: second, later: first }
}

/**
 * Compare snapshot chronology.
 *
 * @param {object} first First snapshot
 * @param {object} second Second snapshot
 * @return {number} Sort result
 */
function compareSnapshots(first, second) {
	if (first.mtime !== second.mtime) {
		return first.mtime - second.mtime
	}
	if (first.kind !== second.kind) {
		return first.kind === 'current' ? 1 : -1
	}
	return first.key < second.key ? -1 : first.key > second.key ? 1 : 0
}

export class ComparisonSnapshotError extends Error {
	constructor(before, after) {
		super('One or more comparison snapshots could not be loaded')
		this.name = 'ComparisonSnapshotError'
		this.before = before
		this.after = after
	}

	get reasons() {
		return [this.before, this.after]
			.filter(({ status }) => status === 'rejected')
			.map(({ reason }) => reason)
	}
}

/**
 * Load both snapshots and retain both rejection reasons.
 *
 * @param {{earlier: object, later: object}} pair Snapshot pair
 * @param {function(object, AbortSignal=): Promise<string>} fetchSnapshot Snapshot loader
 * @param {AbortSignal|undefined} signal Cancellation signal
 * @return {Promise<{beforeContent: string, afterContent: string}>} Both contents
 */
export async function loadVersionComparisonSnapshots(pair, fetchSnapshot, signal) {
	const [before, after] = await Promise.allSettled([
		Promise.resolve().then(() => fetchSnapshot(pair.earlier, signal)),
		Promise.resolve().then(() => fetchSnapshot(pair.later, signal)),
	])
	if (before.status === 'rejected' || after.status === 'rejected') {
		throw new ComparisonSnapshotError(before, after)
	}
	return {
		afterContent: after.value,
		beforeContent: before.value,
	}
}

/** Cache only successfully loaded immutable historical snapshot bodies. */
export class VersionComparisonSnapshotCache {
	#contents = new Map()

	/**
	 * Load one snapshot without caching current, failed, aborted, or in-flight work.
	 *
	 * @param {object} snapshot Snapshot metadata
	 * @param {function(object, AbortSignal=): Promise<string>} fetchSnapshot Snapshot loader
	 * @param {AbortSignal|undefined} signal Cancellation signal
	 * @return {Promise<string>} Snapshot body
	 */
	async load(snapshot, fetchSnapshot, signal) {
		if (snapshot.kind === 'historical' && this.#contents.has(snapshot.fileVersion)) {
			return this.#contents.get(snapshot.fileVersion)
		}

		const content = await fetchSnapshot(snapshot, signal)
		if (snapshot.kind === 'historical' && !signal?.aborted) {
			this.#contents.set(snapshot.fileVersion, content)
		}
		return content
	}

	clear() {
		this.#contents.clear()
	}
}

/** Abort superseded work and identify stale async completions. */
export class ComparisonRequestManager {
	#controller = null
	#generation = 0

	begin() {
		this.#controller?.abort()
		this.#controller = new AbortController()
		const generation = ++this.#generation
		return {
			generation,
			signal: this.#controller.signal,
		}
	}

	isCurrent(generation) {
		return generation === this.#generation
	}

	cancel() {
		this.#controller?.abort()
		this.#controller = null
		this.#generation++
	}
}
