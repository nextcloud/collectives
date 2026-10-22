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
	displayname?: string
}

/**
 * A read-only node for the viewer, built from known properties without a request
 *
 * @param options - Properties of the file
 */
export function fileNode(options: FileNodeOptions): File {
	const { id, source, mime, root, size, mtime, etag, displayname } = options
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
		displayname,
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
	/** Name of the version in the viewer's title */
	displayname?: string
}

/**
 * A page version, or the current page, as the viewer compares it
 *
 * Old versions carry the page's own id and a `/versions/` root, which the
 * Text viewer relies on to keep the editor read-only. The versions store has
 * encoded sources, while nodes take decoded ones and encode them for requests.
 *
 * @param version - Entry from the versions store, or the current version
 */
export function versionNode(version: VersionInfo): File {
	const isVersion = version.filename?.startsWith('/versions/')
	return fileNode({
		id: version.fileId,
		source: decodeSource(version.source),
		mime: version.mime,
		root: isVersion ? `/versions/${getCurrentUser()?.uid}` : getRootPath(),
		size: version.size,
		mtime: new Date(version.mtime),
		etag: version.etag,
		displayname: version.displayname,
	})
}

/**
 * Decode the path of an encoded URL, segment by segment
 *
 * @param source - URL with an encoded path
 */
function decodeSource(source: string): string {
	const { origin, pathname } = new URL(source)
	return origin + pathname.split('/').map(decodeURIComponent).join('/')
}

// Needed for Nextcloud 35 and below. Can be removed once we only support Nextcloud 36+
interface LegacyViewer {
	availableHandlers: { mimes: string[] }[]
	open: (options: { path: string, list?: unknown[] }) => void
	compare: (fileInfo: object, compareFileInfo: object) => void
	close: () => void
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
 *
 * The earlier version is the base of the comparison, regardless of the order
 * of the arguments.
 * Needed for Nextcloud 35 and below. Can be removed once we only support Nextcloud 36+
 *
 * @param first - One of the versions
 * @param second - The other version
 * @param page - The page, for the Viewer app to resolve attachments
 * @param page.id - Id of the page
 * @param page.filePath - Path of the page's file, relative to the user's files root
 */
export async function compareVersions(first: VersionInfo, second: VersionInfo, page: { id: number, filePath: string }): Promise<void> {
	const [earlier, later] = first.mtime <= second.mtime ? [first, second] : [second, first]
	if (canCompare(versionNode(later))) {
		await getViewer().compare(versionNode(later), versionNode(earlier))
		return
	}
	const legacy = legacyViewer()
	if (legacy) {
		// The Viewer app keeps showing the first comparison. Re-open to workaround this.
		legacy.close()
		await new Promise((resolve) => setTimeout(resolve))
		// Text compares Markdown versions in the Viewer app since Nextcloud 35,
		// and resolves attachments with the id and path of the later file. Before,
		// the Viewer app shows the files as they are, and a file with an id would
		// open as the page in an editor.
		// This can be refactored once we only support Nextcloud 35 and above.
		const fileInfo = typeof window.OCA?.Text?.createMarkdownContentComparison === 'function'
			? { ...later, fileid: page.id, filename: page.filePath }
			: { ...later }
		legacy.compare(fileInfo, { ...earlier })
	}
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
