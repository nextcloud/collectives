<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		size="normal"
		class="collective-publish-modal"
		@close="onClose">
		<h2 class="modal-collective-members__name">
			{{ collective.name }}
		</h2>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'

export default {
	name: 'CollectivePublishModal',

	components: {
		NcModal,
	},

	props: {
		collective: {
			required: true,
			type: Object,
		},
	},

	emits: [
		'close',
	],

	methods: {
		onClose() {
			this.$emit('close')
		},
	},
}
</script>

<style lang="scss" scoped>
.collective-publish-modal {
	:deep(.modal-wrapper .modal-container) {
		display: flex !important;
		padding-block: 4px 0;
		padding-inline: 12px;
	}

	:deep(.modal-wrapper .modal-container__content) {
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
}
</style>
