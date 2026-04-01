<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li :id="`page-browser-${type}-${id}`">
		<a
			:class="{ self: id === currentId }"
			:href="id === currentId ? null : '#'"
			class="item"
			@click="$emit('click')">
			<div v-if="emoji" class="icon">
				{{ emoji }}
			</div>
			<CollectivesIcon
				v-else-if="type === 'collective'"
				class="icon"
				:size="20"
				fillColor="var(--color-background-darker)" />
			<PageIcon
				v-else
				class="icon"
				:size="20"
				fillColor="var(--color-background-darker)" />
			<div class="title">
				{{ title }}
			</div>
			<div v-if="id === currentId" class="action-buttons">
				<slot name="currentActions" />
			</div>
		</a>
	</li>
</template>

<script lang="ts">
import type { PropType } from 'vue'

import { t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import CollectivesIcon from '../../Icon/CollectivesIcon.vue'
import PageIcon from '../../Icon/PageIcon.vue'

type ItemType = 'collective' | 'page'

export default defineComponent({
	name: 'ListItem',

	components: {
		CollectivesIcon,
		PageIcon,
	},

	props: {
		id: {
			type: Number,
			required: true,
		},

		emoji: {
			type: String,
			default: null,
		},

		title: {
			type: String,
			required: true,
		},

		type: {
			type: String as PropType<ItemType>,
			required: true,
		},

		currentId: {
			type: Number,
			default: 0,
		},
	},

	emits: [
		'click',
	],

	computed: {
	},

	methods: {
		t,
	},
})
</script>

<style scoped lang="scss">
li a {
	display: flex;
	height: var(--default-clickable-area);
	border-radius: var(--border-radius-element, var(--border-radius-large));
	margin: var(--default-grid-baseline) 0;

	&:not(:last-child) {
		border-bottom: 1px solid var(--color-border);
	}

	&:hover, &:focus, &:active {
		background-color: var(--color-background-hover);
	}

	// Element of the item that is to be copied/moved
	&.self {
		background-color: var(--color-primary-element-light);
	}
}

li a.self {
	cursor: default;

	.icon, .title {
		cursor: default;
	}
}

.icon {
	display: flex;
	justify-content: center;
	align-items: center;
	width: var(--default-clickable-area);
}

.title {
	flex: 1;
	align-content: center;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}

.action-buttons {
	display: flex;
	align-items: center;
	padding: 0 12px;
}
</style>
