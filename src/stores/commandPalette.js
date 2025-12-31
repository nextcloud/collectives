/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

export const useCommandPaletteStore = defineStore('commandPalette', {
	state: () => ({
		isOpen: false,
	}),

	actions: {
		/**
		 * Open the command palette
		 */
		open() {
			this.isOpen = true
		},

		/**
		 * Close the command palette
		 */
		close() {
			this.isOpen = false
		},

		/**
		 * Toggle the command palette open/closed
		 */
		toggle() {
			this.isOpen = !this.isOpen
		},
	},
})
