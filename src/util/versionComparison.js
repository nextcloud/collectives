/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const CURRENT_VERSION_KEY = 'current'

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
 * @return {{options: object[], quarantined: object[]}} Selector state
 */
export function createVersionComparisonState(currentVersion, versions) {
	const current = {
		fileInfo: currentVersion,
		fileVersion: null,
		key: CURRENT_VERSION_KEY,
		kind: 'current',
		label: currentVersion.label,
		mtime: currentVersion.mtime,
		url: currentVersion.source ?? currentVersion.url,
	}
	const candidates = versions.filter((version) => version.mtime !== currentVersion.mtime)
	const identityCounts = candidates.reduce((counts, version) => {
		if (isValidHistoricalIdentity(version.fileVersion)) {
			counts.set(version.fileVersion, (counts.get(version.fileVersion) ?? 0) + 1)
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
			return [{
				fileInfo: version,
				fileVersion,
				key: `version:${fileVersion}`,
				kind: 'historical',
				label: version.label,
				mtime: version.mtime,
				url: version.source ?? version.url,
			}]
		})
		.toSorted(compareSnapshots)

	return {
		options: [current, ...historical.reverse()],
		quarantined,
	}
}

/** @param {unknown} fileVersion DAV version basename */
function isValidHistoricalIdentity(fileVersion) {
	return typeof fileVersion === 'string' && fileVersion.length > 0
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
