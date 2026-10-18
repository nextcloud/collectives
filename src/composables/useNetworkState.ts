/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { subscribe } from '@nextcloud/event-bus'
import { computed, ref } from 'vue'

declare module '@nextcloud/event-bus' {
	export interface NextcloudEvents {
		networkOnline: { success: boolean }
	}
}

const offlineSince = ref<number | null>(navigator.onLine ? null : Date.now())
const networkOnline = computed(() => !offlineSince.value)
let subscribed = false

/**
 * Get network online/offline state
 *
 * The state is shared by all callers and updated by a single pair of
 * event bus subscriptions.
 */
export function useNetworkState() {
	if (!subscribed) {
		subscribed = true

		subscribe('networkOnline', (event) => {
			if (event.success) {
				offlineSince.value = null
			}
		})
		subscribe('networkOffline', () => {
			offlineSince.value = Date.now()
		})
	}

	return { networkOnline, offlineSince }
}
