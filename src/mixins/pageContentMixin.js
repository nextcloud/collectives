import axios from '@nextcloud/axios'
import { mapGetters } from 'vuex'

export default {
	computed: {
		...mapGetters([
			'isPublic',
			'shareTokenParam',
		]),
	},

	methods: {
		/**
		 * Get markdown content of page
		 *
		 * @param {string} davUrl URL to fetch page via DAV
		 */
		async fetchPageContent(davUrl) {
			// Authenticate via share token for public shares
			let axiosConfig = {}
			if (this.isPublic) {
				axiosConfig = {
					auth: {
						username: this.shareTokenParam,
					},
				}
			}

			try {
				const content = await axios.get(davUrl, axiosConfig)
				// content.data will attempt to parse as json
				// but we want the raw text.
				return content.request.responseText
			} catch (e) {
				console.error('Failed to fetch content of page', e)
			}
		},
	},
}
