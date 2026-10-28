/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'alice' }) }))
vi.mock('@nextcloud/axios', () => ({ default: {} }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (_app: string, _key: string, fallback: unknown) => fallback }))
vi.mock('../../apis/collectives/index.js', () => ({}))

const garden = { id: 1, name: 'Garden' }
const kitchen = { id: 2, name: 'Kitchen' }

describe('collectives store', () => {
	let collectivesStore: ReturnType<typeof useCollectivesStore>

	beforeEach(() => {
		setActivePinia(createPinia())
		collectivesStore = useCollectivesStore()
		collectivesStore.$patch({ collectivesState: [garden, kitchen] as never[] })
	})

	describe('printCollective', () => {
		it('is null without print collective', () => {
			expect(collectivesStore.printCollective).toBeNull()
		})

		it('returns the collective set with setPrintCollectiveId', () => {
			collectivesStore.setPrintCollectiveId(kitchen.id)
			expect(collectivesStore.printCollective).toStrictEqual(kitchen)
		})

		it('is null again after resetting the print collective', () => {
			collectivesStore.setPrintCollectiveId(kitchen.id)
			collectivesStore.setPrintCollectiveId(undefined)
			expect(collectivesStore.printCollective).toBeNull()
		})

		it('returns the public collective in public shares', () => {
			useRootStore().$patch({ shareTokenParam: 'token' })
			const publicCollective = { id: 3, name: 'Shared', shareToken: 'token' }
			collectivesStore.$patch({ publicCollectivesState: { token: publicCollective } })

			collectivesStore.setPrintCollectiveId(publicCollective.id)
			expect(collectivesStore.printCollective).toStrictEqual(publicCollective)
		})
	})
})
