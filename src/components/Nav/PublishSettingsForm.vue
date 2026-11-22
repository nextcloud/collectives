<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="publish-settings-form">
		<div class="publish-settings-form__field">
			<label :for="titleId" class="publish-settings-form__label">
				{{ t('collectives', 'Website title') }}
			</label>
			<NcTextField
				:id="titleId"
				:modelValue="title"
				:label="t('collectives', 'Website title')"
				labelOutside
				:error="!isValidTitle(title)"
				:helperText="t('collectives', 'Shown in the browser tab and in the site header.')"
				:maxlength="titleMaxLength"
				required
				@update:modelValue="$emit('update:title', $event)" />
		</div>
		<div class="publish-settings-form__field">
			<!-- TRANSLATORS The website address is the part of the website URL that identifies the published collective -->
			<label :for="slugId" class="publish-settings-form__label">
				{{ t('collectives', 'Website address') }}
			</label>
			<NcNoteCard
				v-if="!slugReadOnly"
				class="publish-settings-form__note"
				type="info"
				:text="t('collectives', 'Choose carefully: the address is permanent once the website is published.')" />
			<div class="publish-settings-form__address">
				<span class="publish-settings-form__address-prefix" aria-hidden="true">
					{{ addressPrefix }}
				</span>
				<NcTextField
					:id="slugId"
					class="publish-settings-form__address-input"
					:modelValue="slug"
					:label="t('collectives', 'Website address')"
					labelOutside
					:error="!slugReadOnly && slug !== '' && (!isValidSlug(slug) || slugTaken)"
					:disabled="slugReadOnly"
					:helperText="slugHelperText"
					:maxlength="slugMaxLength"
					required
					@update:modelValue="$emit('update:slug', $event)" />
			</div>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import uniqueIdMixin from '../../mixins/uniqueIdMixin.js'
import { isValidSlug, SLUG_MAX_LENGTH } from '../../util/staticSiteSlug.js'
import { isValidTitle, TITLE_MAX_LENGTH } from '../../util/staticSiteTitle.js'

export default {
	name: 'PublishSettingsForm',

	components: {
		NcNoteCard,
		NcTextField,
	},

	mixins: [uniqueIdMixin],

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
			// TODO(Publish): Use the base URL provided by the publish service
			addressPrefix: 'https://example.com/sites/',
			slugId: `publish-settings-slug-${this.uniqueId}`,
			slugMaxLength: SLUG_MAX_LENGTH,
			titleId: `publish-settings-title-${this.uniqueId}`,
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
	padding-block: 16px;
	overflow-y: auto;

	&__field {
		display: flex;
		flex-direction: column;
	}

	&__label {
		margin-block-end: 10px;
		font-weight: bold;
	}

	// Higher specificity to override the default margin of the note card
	& &__note {
		margin-block: 0 8px;
	}

	&__address {
		display: flex;
		align-items: flex-start;
	}

	&__address-prefix {
		flex: 0 1 auto;
		min-width: 0;
		height: var(--default-clickable-area);
		padding-inline: 12px;
		overflow: hidden;
		border-start-start-radius: var(--border-radius-element);
		border-end-start-radius: var(--border-radius-element);
		background-color: var(--color-background-dark);
		color: var(--color-text-maxcontrast);
		line-height: var(--default-clickable-area);
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	// The prefix only gets shortened if the input would get narrower than its minimum width
	&__address-input {
		flex: 1 1 150px;
		min-width: 150px;

		// The prefix is attached to the start of the input
		:deep(.input-field__input) {
			border-start-start-radius: 0;
			border-end-start-radius: 0;
		}
	}
}
</style>
