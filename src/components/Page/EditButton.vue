<!-- dirty hack to workaround https://github.com/nextcloud/nextcloud-vue/issues/3231 -->
<template>
	<div>
		<NcButton v-if="!mobile"
			v-tooltip="description"
			:aria-label="description"
			class="titleform-button"
			type="primary"
			@click="$emit('click')">
			<template #icon>
				<NcLoadingIcon v-if="loading"
					:size="20" />
				<CheckIcon v-else-if="editModeAndReady" :size="20" />
				<PencilIcon v-else :size="20" />
			</template>
			{{ title }}
		</NcButton>
		<NcButton v-if="mobile"
			v-tooltip="description"
			:aria-label="description"
			class="titleform-button"
			type="primary"
			@click="$emit('click')">
			<template #icon>
				<NcLoadingIcon v-if="loading"
					:size="20" />
				<CheckIcon v-else-if="editModeAndReady" :size="20" />
				<PencilIcon v-else :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'

export default {
	name: 'EditButton',

	components: {
		CheckIcon,
		NcButton,
		NcLoadingIcon,
		PencilIcon,
	},

	props: {
		editModeAndReady: {
			type: Boolean,
			required: true,
		},
		mobile: {
			type: Boolean,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		description() {
			return this.editModeAndReady ? t('collectives', 'Stop editing') : t('collectives', 'Start editing')
		},
		title() {
			return this.editModeAndReady ? t('collectives', 'Done') : t('collectives', 'Edit')
		},
	},
}
</script>
