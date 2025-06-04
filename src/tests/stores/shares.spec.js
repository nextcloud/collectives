/**
 * @jest-environment jsdom
 */

import { useSharesStore } from '../../stores/shares.js'
import { setActivePinia, createPinia, storeToRefs } from 'pinia'
import { reactive, ref, computed, set } from 'vue'

import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'
import { getShares, createCollectiveShare } from '../../apis/collectives/index.js'

jest.mock('../../stores/collectives.js')
jest.mock('../../stores/root.js')
jest.mock('../../apis/collectives/index.js')

beforeEach(() => {
	// creates a fresh pinia and makes it active
	// so it's automatically picked up by any useStore() call
	// without having to pass it to it: `useStore(pinia)`
	setActivePinia(createPinia())
	useCollectivesStore.mockClear()
	useRootStore.mockClear()
	useRootStore.mockReturnValue({ load: jest.fn(), done: jest.fn() })
	getShares.mockClear()
	createCollectiveShare.mockClear()
})

test('it returns an empty array by default', async () => {
	useCollectivesStore.mockReturnValue(reactive({ currentCollective: { id: 123 } }))
	getShares.mockReturnValue({ data: { data: [] } })
	const store = useSharesStore()
	await store.getShares()
	expect(store.sharesByPageId(3).value).toEqual([])
	expect(useCollectivesStore).toHaveBeenCalledTimes(1)
})

test('it returns a list of shares', async () => {
	useCollectivesStore.mockReturnValue(reactive({ currentCollective: { id: 123 } }))
	const store = useSharesStore()
	getShares.mockReturnValue({
		data: { data: [{ id: 1, pageId: 3, hello: 'world' }] },
	})
	await store.getShares()
	expect(store.sharesByPageId(3).value.length).toEqual(1)
	expect(useCollectivesStore).toHaveBeenCalledTimes(1)
})

test('it updates the list of shares', async () => {
	useCollectivesStore.mockReturnValue(reactive({ currentCollective: { id: 123 } }))
	const store = useSharesStore()
	getShares.mockReturnValue({
		data: { data: [{ pageId: 3, hello: 'world' }] },
	})
	await store.getShares()
	getShares.mockReturnValue({
		data: {
			data: [
				{ id: 1, pageId: 3, hello: 'world' },
				{ id: 2, pageId: 3, hello: 'there' },
			],
		},
	})
	await store.getShares()
	expect(store.sharesByPageId(3).value.length).toEqual(2)
	expect(useCollectivesStore).toHaveBeenCalledTimes(1)
})

test('it updates the list of shares on create', async () => {
	useCollectivesStore.mockReturnValue(reactive({ currentCollective: { id: 123 } }))
	const store = useSharesStore()
	getShares.mockReturnValue({ data: { data: [] } })
	await store.getShares()
	const { shares } = storeToRefs(store)
	const sharesByPageId = computed(
		() => shares.value.filter(s => s.pageId === 3),
		// { onTrigger: console.debug },
	)
	expect(shares.value).toEqual([])
	expect(sharesByPageId.value).toEqual([])
	createCollectiveShare.mockReturnValue({
		data: { data: { pageId: 3, hello: 'world' } },
	})
	await store.createShare(1)
	expect(store.allShares).not.toEqual([])
	expect(store.shares).not.toEqual([])
	expect(shares.value).not.toEqual([])
	expect(shares.value.filter(s => s.pageId === 3)).not.toEqual([])
	expect(sharesByPageId.value).not.toEqual([])
})

test.only('filtering array in object in computed', async () => {
	const shares = reactive({ })
	const sharesByPageId = computed(
		() => shares.a123,
		{ onTrigger: console.debug }
	)
	expect(sharesByPageId.value).toEqual(undefined)
	set(shares, 'a123', [{ pageId: 3, text: 'asdf' }])
	expect(shares.a123.filter(s => s.pageId === 3)).not.toEqual([])
	expect(sharesByPageId.value).not.toEqual(undefined)
})

test('filtering array in computed', async () => {
	const shares = ref([])
	const sharesByPageId = computed(
		() => shares.value.filter(s => s.pageId === 3),
		// { onTrigger: console.debug }
	)
	expect(shares.value).toEqual([])
	expect(sharesByPageId.value).toEqual([])
	shares.value.unshift({ pageId: 3, text: 'asdf' })
	expect(shares.value).not.toEqual([])
	expect(shares.value.filter(s => s.pageId === 3)).not.toEqual([])
	expect(sharesByPageId.value).not.toEqual([])
})

test('it starts with an empty shares list', async () => {
	useCollectivesStore.mockReturnValue(reactive({ currentCollective: { id: 123 } }))
	const store = useSharesStore()
	expect(store.sharesByPageId(3).value).toEqual([])
})

test('it updates the list of shares by page id on create', async () => {
	useCollectivesStore.mockReturnValue(reactive({ currentCollective: { id: 123 } }))
	const store = useSharesStore()
	getShares.mockReturnValue({ data: { data: [] } })
	await store.getShares()
	expect(store.sharesByPageId(3).value).toEqual([])
	createCollectiveShare.mockReturnValue({
		data: { data: { id: 1, pageId: 3, hello: 'world' } },
	})
	await store.createShare(1)
	expect(store.sharesByPageId(3).value).not.toEqual([])
})
