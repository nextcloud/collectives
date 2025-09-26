<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<template v-if="showPrefixString">
			{{ t('collectives', 'Last changed by') }}
		</template>
		<NcUserBubble
			:display-name="lastUserDisplayName"
			:user="lastUserId" />
		<span class="timestamp">
			{{ lastUpdate }}
		</span>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { NcUserBubble } from '@nextcloud/vue'

export default {
	name: 'LastUserBubble',

	components: {
		NcUserBubble,
	},

	props: {
		lastUserId: {
			type: String,
			required: true,
		},

		lastUserDisplayName: {
			type: String,
			required: true,
		},

		timestamp: {
			type: Number,
			required: true,
		},

		showPrefixString: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		lastUpdate() {
			return moment.unix(this.timestamp).fromNow()
		},
	},

	methods: {
		t,
	},
}
</script>
