<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcEmptyContent
		:name="t('collectives', 'Offline')"
		:description="t('collectives', 'Make sure you are online and can reach the server.')">
		<template #icon>
			<CloudOffOutlineIcon />
		</template>
	</NcEmptyContent>
</template>

<script lang="ts">
import { t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import CloudOffOutlineIcon from 'vue-material-design-icons/CloudOffOutline.vue'

export default defineComponent({
	name: 'OfflineContent',

	components: {
		CloudOffOutlineIcon,
		NcEmptyContent,
	},

	setup() {
		return { t }
	},
})
</script>
