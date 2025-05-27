/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { useCollectivesStore } from './collectives.js'
import { usePagesStore } from './pages.js'
import client from '../util/davClient.js'
import davRequest from '../util/davRequest.js'
import { generateRemoteUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { joinPaths, encodePath } from '@nextcloud/paths'
import moment from '@nextcloud/moment'
import { showError, showSuccess } from '@nextcloud/dialogs'

export const useVersionsStore = defineStore('versions', {
	state: () => ({
		selectedVersion: null,
		versions: [],
	}),

	getters: {
		hasVersionsLoaded: (state) => !!state.versions.length,

		currentVersion: () => {
			const collectivesStore = useCollectivesStore()
			const pagesStore = usePagesStore()

			const pageDavPath = `/files/${pagesStore.pageDavPath(pagesStore.currentPage)}`
			return {
				fileId: pagesStore.currentPage.id,
				label: '',
				author: pagesStore.currentPage.lastUserId ?? null,
				filename: pageDavPath,
				basename: pagesStore.currentPage.fileName,
				mime: 'text/markdown',
				etag: '',
				size: pagesStore.currentPage.size,
				type: 'file',
				mtime: pagesStore.currentPage.timestamp * 1000,
				permissions: collectivesStore.currentCollectiveCanEdit ? 'RWD' : 'R',
				url: joinPaths('/remote.php/dav', pageDavPath),
				source: generateRemoteUrl('dav') + pageDavPath,
			}
		},
	},

	actions: {
		selectVersion(version) {
			this.selectedVersion = version
		},

		async getVersions(pageId) {
			const user = getCurrentUser().uid
			const path = `/versions/${user}/versions/${pageId}`
			const response = await client.getDirectoryContents(path, {
				data: davRequest,
				details: true,
			})

			this.versions = response.data
				// filter out root
				.filter(({ mime }) => mime !== '')
				.map(version => this.formatVersion(version, pageId))
		},

		formatVersion(version, pageId) {
			const mtime = moment(version.lastmod).unix() * 1000
			return {
				fileId: pageId,
				label: version.props['version-label'] || '',
				author: version.props['version-author'] ?? null,
				filename: version.filename,
				basename: moment(mtime).format('LLL'),
				mime: version.mime,
				etag: `${version.props.getetag}`,
				size: version.size,
				type: version.type,
				mtime,
				permissions: 'R',
				url: joinPaths('/remote.php/dav', version.filename),
				source: generateRemoteUrl('dav') + encodePath(version.filename),
				fileVersion: version.basename,
			}
		},

		async restoreVersion(version) {
			const pagesStore = usePagesStore()
			const user = getCurrentUser().uid

			try {
				await client.moveFile(
					`/versions/${user}/versions/${version.fileId}/${version.fileVersion}`,
					`/versions/${user}/restore/target`,
				)
			} catch (e) {
				showError(t('collectives', 'Failed to restore {basename} version of {page}.', {
					basename: version.basename,
					page: pagesStore.currentPage.title,
				}))
				console.error('Failed to restore version of page', e)
				return
			}

			this.selectVersion(null)
			this.getVersions(pagesStore.currentPage.id)
			showSuccess(t('collectives', 'Restored {basename} version of {page}.', {
				basename: version.basename,
				page: pagesStore.currentPage.title,
			}))
		},

		async setVersionLabel(version, newLabel) {
			try {
				await client.customRequest(
					version.filename,
					{
						method: 'PROPPATCH',
						data: `<?xml version="1.0"?>
							<d:propertyupdate xmlns:d="DAV:"
								xmlns:oc="http://owncloud.org/ns"
								xmlns:nc="http://nextcloud.org/ns"
								xmlns:ocs="http://open-collaboration-services.org/ns">
							<d:set>
								<d:prop>
									<nc:version-label>${newLabel}</nc:version-label>
								</d:prop>
							</d:set>
							</d:propertyupdate>`,
					},
				)
			} catch (e) {
				showError(t('collectives', 'Failed to set version label for {basename}.', {
					basename: version.basename,
				}))
				console.error('Failed to set version label', e)
				throw e
			}

			showSuccess(t('collectives', 'Set label for {basename}.', {
				basename: version.basename,
			}))
		},

		async deleteVersion(version) {
			const pagesStore = usePagesStore()

			try {
				await client.deleteFile(version.filename)
			} catch (e) {
				showError(t('collectives', 'Failed to delete {basename} version of {page}.', {
					basename: version.basename,
					page: pagesStore.currentPage.title,
				}))
				console.error('Failed to delete version of page', e)
				return
			}

			if (version.basename === this.selectedVersion?.basename) {
				this.selectVersion(null)
			}
			this.getVersions(pagesStore.currentPage.id)
			showSuccess(t('collectives', 'Deleted {basename} version of {page}.', {
				basename: version.basename,
				page: pagesStore.currentPage.title,
			}))
		},
	},
})
