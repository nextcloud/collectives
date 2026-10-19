/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { File, Permission } from '@nextcloud/files'
import { getClient, getDefaultPropfind, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { canCompare, canView, getViewer } from '@nextcloud/viewer'

interface FileNodeOptions {
	id: number
	source: string
	mime?: string
	root: string
	size?: number
	mtime?: Date
	etag?: string
}

/**
 * A read-only node for the viewer, built from known properties without a request
 *
 * @param options - Properties of the file
 */
export function fileNode(options: FileNodeOptions): File {
	const { id, source, mime, root, size, mtime, etag } = options
	return new File({
		id,
		source,
		mime: mime || 'application/octet-stream',
		root,
		owner: getCurrentUser()?.uid ?? null,
		permissions: Permission.READ,
		size,
		mtime,
		attributes: { etag },
	})
}

interface VersionInfo {
	fileId: number
	filename: string
	source: string
	mime: string
	size: number
	mtime: number
	etag: string
}

/**
 * A page version, or the current page, as the viewer compares it
 *
 * Old versions carry the page's own id and a `/versions/` root, which the
 * Text viewer relies on to keep the editor read-only.
 *
 * @param version - Entry from the versions store, or the current version
 */
export function versionNode(version: VersionInfo): File {
	const isVersion = version.filename?.startsWith('/versions/')
	return fileNode({
		id: version.fileId,
		source: version.source,
		mime: version.mime,
		root: isVersion ? `/versions/${getCurrentUser()?.uid}` : getRootPath(),
		size: version.size,
		mtime: new Date(version.mtime),
		etag: version.etag,
	})
}

// Needed for Nextcloud 35 and below. Can be removed once we only support Nextcloud 36+
interface LegacyViewer {
	availableHandlers: { mimes: string[] }[]
	open: (options: { path: string, list?: unknown[] }) => void
	compare: (fileInfo: object, compareFileInfo: object) => void
}

/**
 * The Viewer app, loaded on Nextcloud 35 and older
 * Needed for Nextcloud 35 and below. Can be removed once we only support Nextcloud 36+
 *
 * Text and the PDF viewer register their handlers only with the Viewer app on
 * these versions, so the `@nextcloud/viewer` library doesn't know about them.
 * Newer versions provide an `OCA.Viewer` without `registerHandler`.
 */
export function legacyViewer(): LegacyViewer | undefined {
	const viewer = window.OCA?.Viewer
	return typeof viewer?.registerHandler === 'function' ? viewer : undefined
}

/**
 * Whether the Viewer app can open files of the mimetype
 * Needed for Nextcloud 35 and below. Can be removed once we only support Nextcloud 36+
 *
 * @param mime - Mimetype of the file
 */
export function legacyCanView(mime: string): boolean {
	return legacyViewer()?.availableHandlers.some(({ mimes }) => mimes.includes(mime)) ?? false
}

/**
 * Show two versions of the page side by side, preferably in the viewer library
 * Needed for Nextcloud 35 and below. Can be removed once we only support Nextcloud 36+
 *
 * @param later - The later version
 * @param earlier - The earlier version
 */
export async function compareVersions(later: VersionInfo, earlier: VersionInfo): Promise<void> {
	if (canCompare(versionNode(later))) {
		await getViewer().compare(versionNode(later), versionNode(earlier))
		return
	}
	legacyViewer()?.compare({ ...later }, { ...earlier })
}

type DavStat = Parameters<typeof resultToNode>[0]

/**
 * Open a file of the user's files, fetched over WebDAV so the viewer gets every property it checks
 *
 * @param path - Path relative to the user's files root
 */
export async function openFileAt(path: string): Promise<void> {
	const { data } = (await getClient().stat(`${getRootPath()}${path}`, { details: true, data: getDefaultPropfind() })) as { data: DavStat }
	const node = resultToNode(data)
	if (!(node instanceof File)) {
		throw new Error(`${path} is not a file`)
	}
	const legacy = legacyViewer()
	if (!canView(node) && legacy) {
		// Needed for Nextcloud 35 and below. Can be removed once we only support Nextcloud 36+
		legacy.open({ path, list: [] })
		return
	}
	await getViewer().open([node], node, { enableSidebar: false })
}
