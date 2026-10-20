/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import AdminSettings from './views/AdminSettings.vue'

import '@nextcloud/dialogs/style.css'

const app = createApp(AdminSettings)
app.mount('#collectives-admin-settings')
