<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="modal-scroller">
		<h2>{{ t('collectives', 'Collectives pages') }}</h2>
		<div class="modal-inner">
			<NcTextField v-model="query" :placeholder="t('collectives', 'Search')" />
			<NcEmptyContent>
				<template #icon>
					<CollectivesIcon />
				</template>
			</NcEmptyContent>

			<div class="modal-buttons">
				<NcButton @click="close">
					{{ t('collectives', 'Cancel') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script lang="ts">
import { t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'

export default defineComponent({
	name: 'PagePicker',

	components: {
		CollectivesIcon,
		NcButton,
		NcEmptyContent,
		NcTextField,
	},

	emits: [
		'cancel',
		'submit',
	],

	setup() {
		return { t }
	},

	data() {
		return {
			query: '',
		}
	},

	methods: {
		close() {
			this.$emit('cancel')
		},
	},
})
</script>

<style lang="scss" scoped>
.modal-scroller {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	h2 {
		display: flex;
		margin: 12px 0 20px;
	}
}

.modal-inner {
	box-sizing: border-box;
	display: flex;
	flex-direction: column;
	width: 100%;
	min-height: 400px;
	padding: calc(var(--default-grid-baseline) * 3);
}

.modal-buttons {
	display: flex;
	justify-content: flex-end;
	position: sticky;
	bottom: 0;
}
</style>
