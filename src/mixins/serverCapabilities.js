import { getCapabilities } from '@nextcloud/capabilities'

export default {
	data() {
		return {
			capabilities: getCapabilities(),
		}
	},

	computed: {
		passwordPolicy() {
			return this.capabilities?.password_policy || {}
		},
	},
}
