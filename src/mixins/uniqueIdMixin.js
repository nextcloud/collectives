/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Idea taken from https://github.com/vuejs/vue/issues/5886#issuecomment-308647738
// Replace by `useId` when migrating to Vue3: https://vuejs.org/api/composition-api-helpers.html#useid

let uniqueId = 0

export default {
	beforeCreate() {
		this.uniqueId = uniqueId.toString()
		uniqueId += 1
	},
}
