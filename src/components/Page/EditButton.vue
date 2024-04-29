<!-- dirty hack to workaround https://github.com/nextcloud/nextcloud-vue/issues/3231 -->
<template>
	<div>
		<NcButton :title="description"
			:aria-label="description"
			class="titleform-button"
			type="primary"
			@click="handleClick()">
			<template #icon>
				<NcLoadingIcon v-if="showLoadingIcon"
					:size="20" />
				<CheckIcon v-else-if="isTextEdit" :size="20" />
				<PencilIcon v-else :size="20" />
			</template>
			{{ buttonTitle }}
		</NcButton>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { mapGetters, mapMutations } from 'vuex'

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
		...mapGetters([
			'isTextEdit',
			'loading',
		]),

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
		...mapMutations([
			'setTextEdit',
			'setTextView',
		]),

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
