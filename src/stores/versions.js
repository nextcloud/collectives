/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { encodePath, joinPaths } from '@nextcloud/paths'
import { generateRemoteUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'
import * as davApi from '../apis/dav/index.js'
import { useCollectivesStore } from './collectives.js'
import { usePagesStore } from './pages.js'

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
			const response = await davApi.getVersions(pageId)
			this.versions = response.data
				// filter out root
				.filter(({ mime }) => mime !== '')
				.map((version) => this.formatVersion(version, pageId))
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

			try {
				await davApi.restoreVersion(version.fileId, version.fileVersion)
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

		async setVersionLabel(version, label) {
			try {
				await davApi.setVersionLabel(version.fileId, version.fileVersion, label)
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
				await davApi.deleteVersion(version.fileId, version.fileVersion)
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
