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
			:variant="variant"
			@click="handleClick()">
			<template #icon>
				<NcLoadingIcon
					v-if="showLoadingIcon"
					:size="20" />
				<EyeOutlineIcon v-else-if="isTextEdit" :size="20" />
				<PencilIcon v-else :size="20" />
			</template>
			{{ buttonTitle }}
		</NcButton>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import EyeOutlineIcon from 'vue-material-design-icons/EyeOutline.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'EditButton',

	components: {
		EyeOutlineIcon,
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
			return this.isTextEdit ? t('collectives', 'Preview') : t('collectives', 'Edit')
		},

		variant() {
			return this.isTextEdit ? 'secondary' : 'primary'
		},

		buttonTitle() {
			return this.mobile ? '' : this.title
		},

		showLoadingIcon() {
			return this.loading('pageUpdate') || (this.isTextEdit && this.loading('editor'))
		},
	},

	methods: {
		t,

		...mapActions(usePagesStore, ['setTextEdit', 'setTextPreview']),

		handleClick() {
			if (this.isTextEdit) {
				this.setTextPreview()
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
