/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { encodePath, join } from '@nextcloud/paths'
import { generateRemoteUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'
import * as davApi from '../apis/dav/index.js'
import { useCollectivesStore } from './collectives.js'
import { usePagesStore } from './pages.js'

const preparers = new WeakMap()
const requests = new WeakMap()

export const useVersionsStore = defineStore('versions', {
	state: () => ({
		loadedPageId: null,
		selectedVersion: null,
		versions: [],
	}),

	getters: {
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
				url: join('/remote.php/dav', pageDavPath),
				source: generateRemoteUrl('dav') + pageDavPath,
			}
		},
	},

	actions: {
		registerCurrentSnapshotPreparer(preparer) {
			preparers.set(this, preparer)
			return () => {
				if (preparers.get(this) === preparer) {
					preparers.delete(this)
				}
			}
		},

		async prepareCurrentSnapshot() {
			return preparers.get(this)?.()
		},

		selectVersion(version) {
			this.selectedVersion = version
		},

		getVersions(pageId) {
			const task = Promise.resolve()
				.then(() => davApi.getVersions(pageId))
				.then((response) => {
					const versions = response.data
						.filter(({ mime }) => mime !== '')
						.map((version) => this.formatVersion(version, pageId))
					const currentSnapshot = versions.reduce((latest, version) => !latest || version.mtime > latest.mtime ? version : latest, null)
					if (currentSnapshot) {
						currentSnapshot.isCurrentSnapshot = true
					}
					if (requests.get(this) === task) {
						this.versions = versions
						this.loadedPageId = pageId
					}
					return versions
				})
			requests.set(this, task)
			return task
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
				url: join('/remote.php/dav', version.filename),
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
			await this.getVersions(pagesStore.currentPage.id)
				.catch((error) => console.error('Failed to refresh page versions', error))
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
			await this.getVersions(pagesStore.currentPage.id)
				.catch((error) => console.error('Failed to refresh page versions', error))
			showSuccess(t('collectives', 'Deleted {basename} version of {page}.', {
				basename: version.basename,
				page: pagesStore.currentPage.title,
			}))
		},
	},
})
