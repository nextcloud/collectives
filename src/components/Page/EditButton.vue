<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- dirty hack to workaround https://github.com/nextcloud/nextcloud-vue/issues/3231 -->
<template>
	<div>
		<NcButton
			:title="description"
			:aria-label="description"
			class="titleform-button"
			:class="{ mobile: mobile }"
			variant="primary"
			@click="handleClick()">
			<template #icon>
				<NcLoadingIcon
					v-if="showLoadingIcon"
					:size="20" />
				<CheckIcon v-else-if="isTextEdit" :size="20" />
				<PencilIcon v-else :size="20" />
			</template>
			{{ buttonTitle }}
		</NcButton>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'EditButton',

	components: {
		CheckIcon,
		NcButton,
		NcLoadingIcon,
		PencilIcon,
	},

	props: {
		mobile: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(usePagesStore, ['isTextEdit']),

		description() {
			return this.isTextEdit ? t('collectives', 'Stop editing') : t('collectives', 'Start editing')
		},

		title() {
			return this.isTextEdit ? t('collectives', 'Done') : t('collectives', 'Edit')
		},

		buttonTitle() {
			return this.mobile ? '' : this.title
		},

		showLoadingIcon() {
			return this.loading('pageUpdate') || (this.isTextEdit && this.loading('editor'))
		},
	},

	methods: {
		...mapActions(usePagesStore, ['setTextEdit', 'setTextView']),

		handleClick() {
			if (this.isTextEdit) {
				this.setTextView()
			} else {
				this.setTextEdit()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
button.titleform-button {
	&.mobile {
		padding: 0;
	}
}
</style>
