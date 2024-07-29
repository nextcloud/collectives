/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { generateRemoteUrl } from '@nextcloud/router'
import xmlToVersionsList from '../util/xmlToVersionsList.js'

export const useVersionsStore = defineStore('versions', {
	state: () => ({
		version: null,
		versions: [],
	}),

	getters: {
		hasVersionsLoaded: (state) => !!state.versions.length,
	},

	actions: {
		selectVersion(version) {
			this.version = version
		},

		async getVersions(pageId) {
			const user = getCurrentUser().uid
			const versionsUrl = generateRemoteUrl(`dav/versions/${user}/versions/${pageId}`)
			const response = await axios({
				method: 'PROPFIND',
				url: versionsUrl,
				data: `<?xml version="1.0"?>
<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
 <d:prop>
  <d:getcontentlength />
  <d:getcontenttype />
  <d:getlastmodified />
 </d:prop>
</d:propfind>`,
			})
			this.versions = xmlToVersionsList(response.data).reverse()
		},
	},
})
