<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<PagePreview
		v-if="richObject"
		:notFound
		:link="richObject.link"
		:title="richObject.page?.title || ''"
		:description="richObject.description"
		:emoji="richObject.page?.emoji"
		:lastEdited="richObject.lastEdited"
		:lastUserId="richObject.page?.lastUserId"
		:lastUserDisplayName="richObject.page?.lastUserDisplayName" />
</template>

<script>
import PagePreview from '../components/PagePreview.vue'

export default {
	name: 'PageReferenceWidget',

	components: {
		PagePreview,
	},

	/* eslint-disable vue/no-unused-properties */
	/* eslint-disable vue/no-boolean-default */
	props: {
		richObjectType: {
			type: String,
			default: '',
		},

		richObject: {
			type: Object,
			default: null,
		},

		accessible: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		notFound() {
			return this.accessible === false
		},
	},
}
</script>
