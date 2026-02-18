/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { registerWidget } from '@nextcloud/vue/components/NcRichText'
import PageReferenceWidget from './views/PageReferenceWidget.vue'

registerWidget('collectives_page', (el, { richObjectType, richObject, accessible }) => {
	createApp(PageReferenceWidget, {
		richObjectType,
		richObject,
		accessible,
	}).mount(el)
})
