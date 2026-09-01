<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		size="normal"
		class="collective-publish-modal"
		@close="onClose">
		<div class="modal-publish">
			<h2 class="modal-publish__name">
				{{ t('collectives', 'Publish website for collective {name}', { name: collective.name }) }}
			</h2>
		</div>
	</NcModal>
</template>

<script>
import { t } from '@nextcloud/l10n'
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
		t,

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

.modal-publish {
	height: 550px;
	max-height: 80vh;

	&__name {
		font-size: 21px;
		text-align: center;
	}
}

</style>
