<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="publish-settings-form">
		<!-- TRANSLATORS The web address is the part of the website URL that identifies the published collective -->
		<NcTextField
			:modelValue="slug"
			:label="t('collectives', 'Web address')"
			:error="!slugReadOnly && slug !== '' && (!isValidSlug(slug) || slugTaken)"
			:disabled="slugReadOnly"
			:helperText="slugHelperText"
			:maxlength="slugMaxLength"
			required
			@update:modelValue="$emit('update:slug', $event)" />
		<NcTextField
			:modelValue="title"
			:label="t('collectives', 'Website title')"
			:error="!isValidTitle(title)"
			:maxlength="titleMaxLength"
			required
			@update:modelValue="$emit('update:title', $event)" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { isValidSlug, SLUG_MAX_LENGTH } from '../../util/staticSiteSlug.js'
import { isValidTitle, TITLE_MAX_LENGTH } from '../../util/staticSiteTitle.js'

export default {
	name: 'PublishSettingsForm',

	components: {
		NcTextField,
	},

	props: {
		title: {
			type: String,
			required: true,
		},

		slug: {
			type: String,
			required: true,
		},

		/** The slug of a published website can't be changed */
		slugReadOnly: {
			type: Boolean,
			default: false,
		},

		/** The slug is already used by another website */
		slugTaken: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:slug',
		'update:title',
	],

	data() {
		return {
			slugMaxLength: SLUG_MAX_LENGTH,
			titleMaxLength: TITLE_MAX_LENGTH,
		}
	},

	computed: {
		slugHelperText() {
			if (this.slugReadOnly) {
				return t('collectives', 'The web address can\'t be changed after publishing')
			}
			if (this.slugTaken) {
				return t('collectives', 'This web address is already taken by another website')
			}
			return t('collectives', 'Lowercase letters, numbers and single hyphens')
		},
	},

	methods: {
		t,
		isValidSlug,
		isValidTitle,
	},
}
</script>

<style lang="scss" scoped>
.publish-settings-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	flex: 1 1 auto;
	padding-block: 8px;
	overflow-y: auto;
}
</style>
