import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { generateRemoteUrl } from '@nextcloud/router'
import { SELECT_VERSION, SET_VERSIONS } from './mutations'
import { GET_VERSIONS } from './actions'
import xmlToVersionsList from '../util/xmlToVersionsList'

export default {
	state: {
		version: null,
		versions: [],
	},

	getters: {
		version: (state) => state.version,
	},

	mutations: {
		[SELECT_VERSION](state, version) {
			state.version = version
		},

		[SET_VERSIONS](state, versions) {
			state.versions = versions
		},
	},
	actions: {
		async [GET_VERSIONS]({ commit }, pageId) {
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
			const versions = xmlToVersionsList(response.data).reverse()
			commit(SET_VERSIONS, versions)
		},
	},
}
