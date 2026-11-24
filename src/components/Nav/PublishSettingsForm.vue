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
		<div class="publish-settings-form__field">
			<span :id="themeLabelId" class="publish-settings-form__label">
				{{ t('collectives', 'Theme') }}
			</span>
			<div class="publish-settings-form__themes" role="radiogroup" :aria-labelledby="themeLabelId">
				<label
					v-for="option in themeOptions"
					:key="option.value"
					class="theme-option"
					:class="{ 'theme-option--selected': theme === option.value }">
					<input
						type="radio"
						class="theme-option__input"
						:name="themeName"
						:value="option.value"
						:checked="theme === option.value"
						@change="$emit('update:theme', option.value)">
					<img class="theme-option__preview" :src="option.preview" alt="">
					<strong class="theme-option__title">{{ option.title }}</strong>
					<span class="theme-option__description">
						{{ option.description }}
					</span>
				</label>
			</div>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import themePreviewTopnav from '../../../img/theme-preview-topnav.svg?url'
import themePreviewWiki from '../../../img/theme-preview-wiki.svg?url'
import uniqueIdMixin from '../../mixins/uniqueIdMixin.js'
import { isValidSlug, SLUG_MAX_LENGTH } from '../../util/staticSiteSlug.js'
import { THEME_TOPNAV, THEME_WIKI } from '../../util/staticSiteTheme.js'
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

		theme: {
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
		'update:theme',
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
			themeLabelId: `publish-settings-theme-${this.uniqueId}`,
			themeName: `publish-settings-theme-${this.uniqueId}`,
			themeOptions: [
				{
					value: THEME_WIKI,
					preview: themePreviewWiki,
					title: t('collectives', 'Wiki'),
					description: t('collectives', 'Page tree in a sidebar, like in Collectives. Good for many pages.'),
				},
				{
					value: THEME_TOPNAV,
					preview: themePreviewTopnav,
					title: t('collectives', 'Menu bar'),
					description: t('collectives', 'Top-level pages in a menu bar. Good for a few main pages.'),
				},
			],
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
	// Room for the input borders and focus rings, which are drawn outside and would get cut off by the scroll container
	margin-inline: -2px;
	padding-block: 16px;
	padding-inline: 4px;
	overflow-y: auto;
	container-type: inline-size;

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

	// Border like the one of NcInputField. A gap of 1px shows its end side as separator to the input.
	&__address-prefix {
		--prefix-border-color: var(--color-border-maxcontrast);
		--prefix-border-light: 0 -1px var(--prefix-border-color), 0 0 0 1px color-mix(in srgb, var(--prefix-border-color), 65% transparent);
		--prefix-border-dark: 0 1px var(--prefix-border-color), 0 0 0 1px color-mix(in srgb, var(--prefix-border-color), 65% transparent);
		flex: 0 1 auto;
		min-width: 0;
		margin-inline-end: 1px;
		box-shadow: var(--prefix-border-light);
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

		// The prefix is attached to the start of the input, so the start side of its border is hidden
		:deep(.input-field__input) {
			border-start-start-radius: 0;
			border-end-start-radius: 0;
			clip-path: inset(-4px -4px -4px 0);
		}
	}

	// Both parts look like one field, so the prefix gets the focus ring of the input as well
	&__address:focus-within &__address-prefix {
		box-shadow: 0 0 0 2px var(--color-main-text), 0 0 0 4px var(--color-main-background);
	}

	@media (prefers-color-scheme: dark) {
		&__address-prefix {
			box-shadow: var(--prefix-border-dark);
		}
	}

	&__themes {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 12px;
	}

	@container (width < 500px) {
		&__themes {
			grid-template-columns: 1fr;
		}
	}
}

[data-theme-dark] .publish-settings-form__address-prefix {
	box-shadow: var(--prefix-border-dark);
}

[data-theme-light] .publish-settings-form__address-prefix {
	box-shadow: var(--prefix-border-light);
}

.theme-option {
	position: relative;
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 12px;
	border: 2px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	cursor: pointer;
	opacity: 0.6;
	transition: opacity 0.1s ease-in-out, border-color 0.1s ease-in-out;

	&:hover {
		border-color: var(--color-border-maxcontrast);
		opacity: 0.85;
	}

	&:has(.theme-option__input:focus-visible) {
		outline: 2px solid var(--color-main-text);
		outline-offset: 2px;
	}

	&--selected {
		border-color: var(--color-primary-element);
		opacity: 1;
	}

	// Selection is handled by the card itself, so the native control stays accessible but invisible
	&__input {
		position: absolute;
		top: 0;
		inset-inline-start: 0;
		width: 1px;
		height: 1px;
		margin: 0;
		opacity: 0;
	}

	&__preview {
		display: block;
		width: 100%;
		aspect-ratio: 200 / 104;
		margin-block-end: 4px;
		overflow: hidden;
		border: 1px solid var(--color-border-dark);
		border-radius: var(--border-radius);
		object-fit: contain;
		pointer-events: none;
	}

	&__description {
		color: var(--color-text-maxcontrast);
	}
}
</style>
