/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { App } from 'vue'

import { createPinia } from 'pinia'
import { createApp } from 'vue'
import {
	NcCustomPickerRenderResult,
	registerCustomPickerElement,
	registerWidget,
} from '@nextcloud/vue/components/NcRichText'
import PagePicker from './views/PagePicker.vue'
import PageReferenceWidget from './views/PageReferenceWidget.vue'

registerWidget('collectives_page', (el, { richObjectType, richObject, accessible }) => {
	createApp(PageReferenceWidget, {
		richObjectType,
		richObject,
		accessible,
	}).mount(el)
})

registerCustomPickerElement('collectives-ref-pages', (el, { providerId, accessible }) => {
	const pinia = createPinia()
	const app = createApp(PagePicker, { providerId, accessible })
	app.use(pinia)
	app.mount(el)
	return new NcCustomPickerRenderResult(el, app)
}, (el, renderResult) => {
	(renderResult.object as App)?.unmount()
}, 'normal')
