/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

/**
 * Observe the real Text API without replacing its session/network behavior.
 * Test-controlled failures/delays are used only when explicitly armed.
 *
 * @param page Browser page before navigation.
 */
export async function observeTextEditors(page: Page) {
	await page.addInitScript(() => {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		const w = window as any
		const probe = w.collectivesTextProbe = {
			calls: [] as Array<{ writable: boolean, loaded: boolean, resolved: boolean, fileId: number }>,
			failNext: false,
			holdNext: false,
			failSave: false,
			failSaveAfter: -1,
			release: null as null | (() => void),
		}
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		const instrument = (text: any) => {
			// Text installs createEditor after assigning the OCA.Text namespace.
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			const wrap = (create: any) => async function(this: unknown, options: any) {
				const writable = options.readOnly === false && options.useSession !== false
				const call = { writable, loaded: false, resolved: false, fileId: options.fileId }
				probe.calls.push(call)
				if (writable && probe.failNext) {
					probe.failNext = false
					throw new Error('Injected editor initialization failure')
				}
				if (writable && probe.holdNext) {
					probe.holdNext = false
					await new Promise<void>((resolve) => {
						probe.release = resolve
					})
				}
				const instance = await create.call(this, {
					...options,
					onLoaded: () => {
						call.loaded = true
						options.onLoaded?.()
					},
				})
				call.resolved = true
				if (writable) {
					const save = instance.save.bind(instance)
					instance.save = () => {
						if (probe.failSave || probe.failSaveAfter === 0) {
							return Promise.resolve(false)
						}
						if (probe.failSaveAfter > 0) {
							probe.failSaveAfter--
						}
						return save()
					}
				}
				return instance
			}
			let wrapped = typeof text.createEditor === 'function' ? wrap(text.createEditor) : text.createEditor
			Object.defineProperty(text, 'createEditor', {
				configurable: true,
				get: () => wrapped,
				set: (value) => {
					wrapped = wrap(value)
				},
			})
			return text
		}
		// Core may replace window.OCA before Text installs its API.
		const namespaces = new WeakSet<object>()
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		const observeNamespace = (namespace: any) => {
			if (namespaces.has(namespace)) {
				return namespace
			}
			namespaces.add(namespace)
			let text = namespace.Text
			if (text) {
				text = instrument(text)
			}
			Object.defineProperty(namespace, 'Text', {
				configurable: true,
				get: () => text,
				set: (value) => {
					text = instrument(value)
				},
			})
			return namespace
		}
		let oca = observeNamespace(w.OCA || {})
		Object.defineProperty(w, 'OCA', {
			configurable: true,
			get: () => oca,
			set: (value) => {
				oca = observeNamespace(value)
			},
		})
	})
}

/**
 * Wait for every editor started during mounting, including a hidden writer.
 * This deliberately does not require absence of a writer: the baseline must
 * reach the WebDAV PUT and fail there with 423, after acquiring its Text lock.
 *
 * @param page Browser page with the Text observer installed.
 */
export async function waitForTextEditors(page: Page) {
	await page.waitForFunction(() => {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		const calls = (window as any).collectivesTextProbe.calls
		return calls.length > 0 && calls.every((call: { loaded: boolean, resolved: boolean }) => call.loaded && call.resolved)
	})
	const hasWriter = await page.evaluate(() => {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		return (window as any).collectivesTextProbe.calls.some((call: { writable: boolean }) => call.writable)
	})
	if (hasWriter) {
		// Some Text releases broadcast onLoaded globally. A mounted editable
		// document also proves the hidden session editor itself has initialized.
		await page.locator('[data-cy-collectives="editor"] .ProseMirror[contenteditable="true"]').waitFor({ state: 'attached' })
	}
	await page.evaluate(() => new Promise<void>((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve()))))
}
