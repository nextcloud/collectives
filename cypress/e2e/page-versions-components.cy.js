/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { createApp, h, nextTick, ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import SidebarTabVersions from '../../src/components/PageSidebar/SidebarTabVersions.vue'
import VersionComparisonDialog from '../../src/components/PageSidebar/VersionComparisonDialog.vue'
import { useCollectivesStore } from '../../src/stores/collectives.js'
import { usePagesStore } from '../../src/stores/pages.js'
import { useRootStore } from '../../src/stores/root.js'
import { useVersionsStore } from '../../src/stores/versions.js'
import { VERSION_COMPARISON_LIMITS } from '../../src/util/versionComparison.js'

async function waitFor(condition, message = 'Expected component state was not reached') {
	for (let attempt = 0; attempt < 100; attempt++) {
		if (condition()) {
			return
		}
		await new Promise((resolve) => setTimeout(resolve))
	}
	throw new Error(typeof message === 'function' ? message() : message)
}

function deferred() {
	let resolve
	const promise = new Promise((resolvePromise) => {
		resolve = resolvePromise
	})
	return { promise, resolve }
}

async function mountSidebarTabVersions(getVersions) {
	document.head.setAttribute('data-user', 'component-user')
	document.head.setAttribute('data-user-displayname', 'Component User')
	window.history.replaceState({}, '', '/Atlas/Page')
	const router = createRouter({
		history: createWebHistory(),
		routes: [{ path: '/:pathMatch(.*)*', component: { render: () => null } }],
	})
	const pinia = createPinia()
	setActivePinia(pinia)
	const rootStore = useRootStore()
	const collectivesStore = useCollectivesStore()
	const pagesStore = usePagesStore()
	const versionsStore = useVersionsStore()
	rootStore.$patch({ collectiveId: 1, pageId: 7, showingSidebar: true })
	collectivesStore.collectivesState = [{ id: 1, name: 'Atlas', canEdit: true, canShare: true, isPageShare: false }]
	pagesStore.allPages = {
		1: [{ id: 7, parentId: 0, title: 'Page', fileName: 'Readme.md', timestamp: 1, size: 10 }],
	}
	Object.defineProperty(versionsStore, 'currentVersion', {
		configurable: true,
		value: { fileId: 7, mtime: 1000, size: 10, source: '/current', url: '/current' },
	})
	versionsStore.getVersions = getVersions
	const pageId = ref(7)
	const component = ref()
	const element = document.createElement('div')
	document.body.append(element)
	const app = createApp({
		render: () => h(SidebarTabVersions, {
			ref: component,
			pageId: pageId.value,
			pageTimestamp: 1,
		}),
	})
	app.use(pinia)
	app.use(router)
	app.mount(element)
	await router.isReady()
	await nextTick()
	return { app, component, element, pageId, rootStore }
}

async function mountVersionComparisonDialog(currentVersion, versions) {
	const pinia = createPinia()
	setActivePinia(pinia)
	const versionsStore = useVersionsStore(pinia)
	window.history.replaceState({}, '', '/Atlas/Page')
	const router = createRouter({
		history: createWebHistory(),
		routes: [{ path: '/:pathMatch(.*)*', component: { render: () => null } }],
	})
	const component = ref()
	const element = document.createElement('div')
	document.body.append(element)
	const app = createApp({
		render: () => h(VersionComparisonDialog, {
			ref: component,
			currentVersion,
			versions,
			filePath: '/Atlas/Page.md',
		}),
	})
	app.use(pinia)
	app.use(router)
	app.mount(element)
	await router.isReady()
	await nextTick()
	Object.defineProperty(document.documentElement, 'clientWidth', { configurable: true, value: Cypress.config('viewportWidth') })
	window.dispatchEvent(new Event('resize'))
	return { app, component, element, router, versionsStore }
}

function comparisonFixture(size = 10) {
	const currentVersion = { fileId: 7, mtime: 3000, size, source: '/current?file=7', url: '/current?file=7' }
	const historical = { fileVersion: '1', label: 'Old', mtime: 1000, size, source: '/historical?file=7', url: '/historical?file=7' }
	const currentSnapshot = { ...currentVersion, fileVersion: '3', isCurrentSnapshot: true }
	return { currentVersion, historical, versions: [historical, currentSnapshot] }
}

describe('Mounted page version components', () => {
	it('keeps the active page loading while a stale page request completes', () => {
		cy.then(async () => {
			const consoleError = Cypress.sinon.spy(console, 'error')
			const first = deferred()
			const second = deferred()
			const getVersions = Cypress.sinon.stub()
			getVersions.withArgs(7).returns(first.promise)
			getVersions.withArgs(8).returns(second.promise)
			const mounted = await mountSidebarTabVersions(getVersions)

			mounted.pageId.value = 8
			await waitFor(() => getVersions.callCount === 2)
			first.resolve([])
			await first.promise
			await nextTick()
			expect(mounted.component.value.loadPending).to.equal(true)
			expect(mounted.rootStore.loading('versions')).to.equal(true)

			second.resolve([])
			await second.promise
			await waitFor(() => mounted.rootStore.loading('versions') === false)
			expect(mounted.component.value.loadPending).to.equal(false)
			expect(mounted.rootStore.loading('versions')).to.equal(false)
			expect(mounted.component.value.error).to.equal('')
			expect(consoleError).not.to.have.been.calledWithMatch('Failed to get page versions')

			mounted.app.unmount()
			mounted.element.remove()
			consoleError.restore()
		})
	})

	it('reports an expired selection when current preparation removes its historical version', () => {
		cy.then(async () => {
			const fixture = comparisonFixture()
			window.OCA ??= {}
			const createComparison = Cypress.sinon.stub().resolves({ destroy: Cypress.sinon.stub() })
			window.OCA.Text = { createMarkdownContentComparison: createComparison }
			const fetchSnapshot = Cypress.sinon.stub(window, 'fetch')
			const mounted = await mountVersionComparisonDialog(fixture.currentVersion, fixture.versions)
			mounted.versionsStore.registerCurrentSnapshotPreparer(Cypress.sinon.stub().resolves([fixture.versions[1]]))

			try {
				mounted.component.value.openSelector()
				await waitFor(() => [...document.querySelectorAll('.modal-wrapper button')]
					.some(({ textContent }) => textContent.trim() === 'Compare'))
				const compare = [...document.querySelectorAll('.modal-wrapper button')]
					.find(({ textContent }) => textContent.trim() === 'Compare')
				compare.click()

				await waitFor(() => document.querySelector('.version-comparison-dialog [role="alert"]'))
				expect(document.querySelector('.version-comparison-dialog [role="alert"]').textContent)
					.to.contain('One of the selected versions has expired or was removed.')
				expect(fetchSnapshot).not.to.have.been.called
				expect(createComparison).not.to.have.been.called
				expect(new URL(window.location.href).searchParams.has('compareFrom')).to.equal(false)
				expect(new URL(window.location.href).searchParams.has('compareTo')).to.equal(false)
			} finally {
				mounted.app.unmount()
				mounted.element.remove()
				fetchSnapshot.restore()
			}
		})
	})

	it('publishes fresh bounded current previews and retries through the mounted dialog', () => {
		cy.then(async () => {
			const fixture = comparisonFixture(VERSION_COMPARISON_LIMITS.maximumSnapshotBytes + 1)
			window.OCA ??= {}
			window.OCA.Text = { createMarkdownContentComparison: Cypress.sinon.stub() }
			let currentLoads = 0
			const fetchSnapshot = Cypress.sinon.stub(window, 'fetch').callsFake(async (url) => {
				const content = String(url).includes('/current')
					? `fresh-current-${++currentLoads}`
					: 'historical-preview'
				return new Response(content)
			})
			const mounted = await mountVersionComparisonDialog(fixture.currentVersion, fixture.versions)
			const prepare = Cypress.sinon.stub().resolves(fixture.versions)
			mounted.versionsStore.registerCurrentSnapshotPreparer(prepare)

			mounted.component.value.openFor(fixture.historical)
			await waitFor(
				() => document.querySelectorAll('.version-comparison-dialog__preview').length === 2,
				() => `Expected two previews; prepare calls: ${prepare.callCount}; fetch calls: ${fetchSnapshot.callCount}; dialog: ${document.querySelector('.version-comparison-dialog')?.textContent}`,
			)
			expect(document.querySelector('.version-comparison-dialog').textContent).to.contain('fresh-current-1')
			const urls = fetchSnapshot.args.map(([url]) => String(url))
			expect(urls).to.include('/historical?file=7')
			const currentUrl = new URL(urls.find((url) => url.includes('/current')))
			expect(currentUrl.searchParams.get('file')).to.equal('7')
			expect(currentUrl.searchParams.get('timestamp')).to.match(/^\d{13}$/)
			expect(fetchSnapshot.args.every(([, options]) => options.headers.Range === `bytes=0-${VERSION_COMPARISON_LIMITS.maximumPreviewBytes - 1}`)).to.equal(true)

			const retry = [...document.querySelectorAll('button')]
				.find((button) => button.textContent.includes('Retry'))
			expect(retry).to.not.equal(undefined)
			retry.click()
			await waitFor(
				() => document.querySelector('.version-comparison-dialog').textContent.includes('fresh-current-2'),
				`Expected retry preview; fetch calls: ${fetchSnapshot.callCount}`,
			)
			expect(prepare.callCount).to.equal(2)

			mounted.app.unmount()
			mounted.element.remove()
			fetchSnapshot.restore()
		})
	})

	it('uses the Text renderer size selected at compare time', () => {
		cy.then(async () => {
			const fixture = comparisonFixture()
			window.OCA ??= {}
			window.OCA.Text = {}
			const fetchSnapshot = Cypress.sinon.stub(window, 'fetch').callsFake(async () => {
				let sent = false
				return {
					body: { getReader: () => ({
						cancel: async () => {},
						read: async () => {
							if (sent) {
								return { done: true }
							}
							sent = true
							return { done: false, value: new TextEncoder().encode('snapshot') }
						},
					}) },
					headers: new Headers(),
					ok: true,
					status: 200,
				}
			})
			const mounted = await mountVersionComparisonDialog(fixture.currentVersion, fixture.versions)
			mounted.versionsStore.registerCurrentSnapshotPreparer(Cypress.sinon.stub().resolves(fixture.versions))
			try {
				mounted.component.value.openSelector()
				await waitFor(() => [...document.querySelectorAll('.modal-wrapper button')]
					.some(({ textContent }) => textContent.trim() === 'Compare'))
				const createComparison = Cypress.sinon.stub().resolves({ destroy: Cypress.sinon.stub() })
				window.OCA.Text.createMarkdownContentComparison = createComparison
				const compare = [...document.querySelectorAll('.modal-wrapper button')]
					.find(({ textContent }) => textContent.trim() === 'Compare')
				compare.click()

				await waitFor(
					() => createComparison.calledOnce,
					() => `Expected factory call; fetch calls: ${fetchSnapshot.callCount}; dialog: ${document.querySelector('.modal-wrapper')?.textContent}`,
				)
				expect(document.querySelector('.modal-wrapper--full')).not.to.equal(null)
			} finally {
				mounted.app.unmount()
				mounted.element.remove()
				fetchSnapshot.restore()
			}
		})
	})
})
