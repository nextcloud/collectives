/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
