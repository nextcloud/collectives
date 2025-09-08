/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerWidget } from '@nextcloud/vue/dist/Components/NcRichText.js'
import Vue from 'vue'
import PageReferenceWidget from './views/PageReferenceWidget.vue'

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

registerWidget('collectives_page', (el, { richObjectType, richObject, accessible }) => {
	const Widget = Vue.extend(PageReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})
