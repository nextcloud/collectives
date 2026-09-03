/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { isValidComparisonId } from './versionComparisonRoute.js'

/* eslint-disable jsdoc/require-jsdoc */

export const CURRENT_VERSION_KEY = 'current'

export const VERSION_COMPARISON_LIMITS = Object.freeze({
	maximumCachedBytes: 4_000_000,
	maximumCachedEntries: 2,
	maximumPreviewBytes: 16_384,
	maximumPreviewCharacters: 4_096,
	maximumSnapshotBytes: 2_000_000,
})
const LIMITS = VERSION_COMPARISON_LIMITS

const CURRENT_KIND = 'current'
const CURRENT_ROUTE_NAMESPACE = 'current'
const HISTORICAL_KIND = 'historical'
const HISTORICAL_ROUTE_NAMESPACE = 'version'

export function selectVersionComparisonRenderer({ comparisonFactory, viewerCompare, isMobile }) {
	return typeof comparisonFactory === 'function'
		? 'semantic'
		: !isMobile && typeof viewerCompare === 'function' ? 'viewer' : 'unsupported'
}

export function createVersionComparisonState(currentVersion, versions) {
	const currentSnapshot = versions.find(({ isCurrentSnapshot }) => isCurrentSnapshot)
		?? versions.find(({ mtime }) => mtime === currentVersion.mtime)
	const currentMtime = currentSnapshot?.mtime ?? currentVersion.mtime
	const currentSnapshotId = validIdentity(currentSnapshot?.fileVersion) ? currentSnapshot.fileVersion : String(Math.floor(currentMtime / 1000))
	const currentRouteId = routeId(CURRENT_ROUTE_NAMESPACE, currentSnapshotId)
	const current = {
		etag: currentSnapshot ? currentSnapshot.etag : currentVersion.etag,
		fileInfo: currentVersion,
		key: CURRENT_VERSION_KEY,
		kind: CURRENT_KIND,
		mtime: currentMtime,
		routeAliases: [CURRENT_VERSION_KEY],
		routeId: currentRouteId,
		size: currentSnapshot ? currentSnapshot.size : currentVersion.size,
		url: currentSnapshot ? currentSnapshot.source ?? currentSnapshot.url : currentVersion.source ?? currentVersion.url,
	}
	const candidates = versions.filter((version) => version !== currentSnapshot)
	const identityCounts = new Map()
	for (const { fileVersion } of versions) {
		if (validIdentity(fileVersion)) {
			identityCounts.set(fileVersion, (identityCounts.get(fileVersion) ?? 0) + 1)
		}
	}
	const duplicateIdentities = new Set([...identityCounts].filter(([, count]) => count > 1).map(([fileVersion]) => fileVersion))
	let quarantinedCount = 0
	const historical = candidates
		.flatMap((version) => {
			const fileVersion = version.fileVersion
			if (!validIdentity(fileVersion) || duplicateIdentities.has(fileVersion)) {
				quarantinedCount++
				return []
			}
			const route = routeId(HISTORICAL_ROUTE_NAMESPACE, fileVersion)
			const previousCurrentRouteId = routeId(CURRENT_ROUTE_NAMESPACE, fileVersion)
			return [{
				fileInfo: version,
				fileVersion,
				key: route,
				kind: HISTORICAL_KIND,
				label: version.label,
				mtime: version.mtime,
				routeAliases: previousCurrentRouteId === currentRouteId ? [] : [previousCurrentRouteId],
				routeId: route,
				size: version.size,
				url: version.source ?? version.url,
			}]
		})
		.toSorted(compare)

	return {
		ambiguousRouteIds: [...duplicateIdentities]
			.flatMap((fileVersion) => [routeId(HISTORICAL_ROUTE_NAMESPACE, fileVersion), routeId(CURRENT_ROUTE_NAMESPACE, fileVersion)])
			.filter((routeId) => routeId !== currentRouteId),
		options: [current, ...historical.reverse()],
		quarantinedCount,
	}
}

function routeId(namespace, identity) {
	return `${namespace}:${identity}`
}

function validIdentity(fileVersion) {
	return isValidComparisonId(fileVersion)
		&& isValidComparisonId(routeId(HISTORICAL_ROUTE_NAMESPACE, fileVersion))
		&& isValidComparisonId(routeId(CURRENT_ROUTE_NAMESPACE, fileVersion))
}

export function normalizeVersionComparisonPair(first, second) {
	if (!first || !second || first.key === second.key) {
		throw new TypeError('Two distinct versions are required')
	}
	return compare(first, second) <= 0 ? { earlier: first, later: second } : { earlier: second, later: first }
}

function compare(first, second) {
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
		this.name = new.target.name
		this.reasons = [before, after].filter(({ status }) => status === 'rejected').map(({ reason }) => reason)
	}
}

export class ComparisonSnapshotEncodingError extends Error {
	constructor(cause) {
		super('Comparison snapshot contains invalid UTF-8', { cause })
		this.name = new.target.name
	}
}

export class ComparisonSnapshotLimitError extends Error {
	constructor(snapshots) {
		super('One or more comparison snapshots exceed the comparison size limit')
		this.name = new.target.name
		this.snapshots = snapshots
	}
}

export function classifyComparisonSnapshotError(error) {
	if (error.reasons.length > 0 && error.reasons.every((reason) => reason instanceof ComparisonSnapshotEncodingError)) {
		return 'encoding'
	}
	const statuses = error.reasons.map((reason) => reason?.response?.status)
	const count = error.reasons.length === 2 ? 'two' : 'one'
	if (statuses.every((status) => status === 403)) {
		return `permission-${count}`
	}
	if (statuses.every((status) => status === 404 || status === 410)) {
		return `expired-${count}`
	}
	return 'network'
}

export function isVersionComparisonCancellation(error) {
	return error?.name === 'AbortError' || error?.name === 'CanceledError' || error?.__CANCEL__ === true
}

export class CurrentSnapshotPreparationError extends Error {
	constructor(cause) {
		super('Current editor bytes could not be prepared', { cause })
		this.name = new.target.name
	}
}

export async function prepareVersionComparisonPair(pair, prepareCurrentSnapshot, resolvePair) {
	if (pair.earlier.kind !== 'current' && pair.later.kind !== 'current') {
		return pair
	}
	let prepared
	try {
		prepared = await prepareCurrentSnapshot()
	} catch (error) {
		throw new CurrentSnapshotPreparationError(error)
	}
	if (prepared === undefined) {
		throw new CurrentSnapshotPreparationError()
	}
	return resolvePair(prepared)
}

export async function loadVersionComparisonSnapshots(pair, fetchSnapshot, signal) {
	const unboundedSnapshots = [pair.earlier, pair.later].filter(({ size }) => !Number.isSafeInteger(size) || size < 0 || size > LIMITS.maximumSnapshotBytes)
	if (unboundedSnapshots.length > 0) {
		throw new ComparisonSnapshotLimitError(unboundedSnapshots)
	}
	const load = (snapshot) => Promise.resolve().then(() => fetchSnapshot(snapshot, signal))
	const [before, after] = await Promise.allSettled([load(pair.earlier), load(pair.later)])
	const limitedSnapshots = [before, after].filter(({ status, reason }) => status === 'rejected' && reason instanceof ComparisonSnapshotLimitError).flatMap(({ reason }) => reason.snapshots)
	if (limitedSnapshots.length > 0) {
		throw new ComparisonSnapshotLimitError(limitedSnapshots)
	}
	if (before.status === 'rejected' || after.status === 'rejected') {
		throw new ComparisonSnapshotError(before, after)
	}
	return { afterContent: contentOf(after.value), beforeContent: contentOf(before.value) }
}

function contentOf(loaded) {
	if (typeof loaded?.content === 'string' && Number.isSafeInteger(loaded.byteLength) && loaded.byteLength >= 0) {
		return loaded.content
	}
	throw new TypeError('Snapshot loader must return measured content')
}

export async function loadBoundedSnapshotBody(snapshot, fetchSnapshot = fetch, signal) {
	return readSnapshot(snapshot, fetchSnapshot, signal, false)
}

export async function loadBoundedSnapshotPreview(snapshot, fetchPreview = fetch, signal) {
	return readSnapshot(snapshot, fetchPreview, signal, true)
}

async function readSnapshot(snapshot, fetchSnapshot, signal, preview) {
	const limit = preview ? LIMITS.maximumPreviewBytes : LIMITS.maximumSnapshotBytes
	let url = snapshot.url
	if (snapshot.kind === CURRENT_KIND) {
		const currentUrl = new URL(url, window.location.href)
		currentUrl.searchParams.set('timestamp', Date.now())
		url = currentUrl.href
	}
	const response = await fetchSnapshot(url, { credentials: 'same-origin', ...(preview ? { headers: { Range: `bytes=0-${limit - 1}` } } : {}), signal })
	if (!response.ok) {
		const error = new Error(`Snapshot request failed with status ${response.status}`)
		error.response = { status: response.status }
		throw error
	}
	const length = Number(response.headers?.get?.('content-length'))
	if (!preview && Number.isFinite(length) && length > limit) {
		await response.body?.cancel?.()
		throw new ComparisonSnapshotLimitError([snapshot])
	}
	const reader = response.body?.getReader()
	if (!reader) {
		throw new Error('Snapshot response is not streamable')
	}

	const decoder = new TextDecoder('utf-8', { fatal: true })
	let bytes = 0
	const chunks = []
	let truncated = preview && snapshot.size > limit
	while (bytes < limit) {
		const { done, value } = await reader.read()
		if (done) {
			break
		}
		const copied = Math.min(value.byteLength, limit - bytes)
		if (!preview && copied < value.byteLength) {
			await reader.cancel()
			throw new ComparisonSnapshotLimitError([snapshot])
		}
		try {
			chunks.push(decoder.decode(value.subarray(0, copied), { stream: true }))
		} catch (error) {
			await reader.cancel()
			throw preview ? error : new ComparisonSnapshotEncodingError(error)
		}
		bytes += copied
		if (preview && (copied < value.byteLength || bytes === limit)) {
			truncated = true
			await reader.cancel()
			break
		}
	}
	if (!preview && bytes === limit) {
		const { done } = await reader.read()
		if (!done) {
			await reader.cancel()
			throw new ComparisonSnapshotLimitError([snapshot])
		}
	}

	try {
		chunks.push(decoder.decode())
	} catch (error) {
		if (!preview) {
			throw new ComparisonSnapshotEncodingError(error)
		}
		truncated = true
	}
	let content = chunks.join('')
	if (!preview) {
		return { byteLength: bytes, content }
	}
	const chars = Array.from(content)
	if (chars.length > LIMITS.maximumPreviewCharacters) {
		content = chars.slice(0, LIMITS.maximumPreviewCharacters).join('')
		truncated = true
	}
	return { content, truncated }
}

export class VersionComparisonSnapshotCache {
	constructor({ maximumBytes = LIMITS.maximumCachedBytes, maximumEntries = LIMITS.maximumCachedEntries } = {}) {
		this.entries = new Map()
		this.byteLimit = maximumBytes
		this.entryLimit = maximumEntries
		this.bytes = 0
	}

	async load(snapshot, fetchSnapshot, signal) {
		const key = JSON.stringify([snapshot.fileInfo?.id ?? snapshot.fileInfo?.fileId ?? snapshot.fileId, snapshot.fileVersion, snapshot.fileInfo?.etag ?? snapshot.etag, snapshot.url])
		if (snapshot.kind === 'historical' && this.entries.has(key)) {
			const cached = this.entries.get(key)
			this.entries.delete(key)
			this.entries.set(key, cached)
			return cached
		}

		const loaded = await fetchSnapshot(snapshot, signal)
		contentOf(loaded)
		if (snapshot.kind === 'historical' && !signal?.aborted) {
			const bytes = loaded.byteLength
			if (bytes <= this.byteLimit && this.entryLimit > 0) {
				while (this.entries.size >= this.entryLimit || this.bytes + bytes > this.byteLimit) {
					const oldestKey = this.entries.keys().next().value
					const oldest = this.entries.get(oldestKey)
					this.entries.delete(oldestKey)
					this.bytes -= oldest.byteLength
				}
				this.entries.set(key, loaded)
				this.bytes += bytes
			}
		}
		return loaded
	}

	clear() {
		this.entries.clear()
		this.bytes = 0
	}
}

export function createComparisonRequestManager() {
	let controller = null
	let generation = 0
	return {
		begin() {
			controller?.abort()
			controller = new AbortController()
			return { generation: ++generation, signal: controller.signal }
		},
		isCurrent: (candidate) => candidate === generation,
		cancel() {
			controller?.abort()
			controller = null
			generation++
		},
	}
}
