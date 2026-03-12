<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="active" class="filelist-collectives-wrapper">
		<div class="infobox">
			<InformationIcon fillColor="var(--color-primary-element)" />

			<div class="content">
				{{ t('collectives', 'The content of this folder is best viewed in the Collectives app.') }}

				<a :href="collectivesLink">
					<NcButton
						:aria-label="t('collectives', 'Open in Collectives')"
						variant="primary"
						class="button">
						{{ t('collectives', 'Open in Collectives') }}
					</NcButton>
				</a>
			</div>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import InformationIcon from 'vue-material-design-icons/InformationOutline.vue'

const collectivesFolder = loadState('collectives', 'user_folder', null)

export default {
	name: 'FileListInfo',

	components: {
		InformationIcon,
		NcButton,
	},

	props: {
		path: {
			type: String,
			required: true,
		},
	},

	expose: ['setPath'],

	data() {
		return {
			internalPath: this.path,
			active: false,
		}
	},

	computed: {
		collectivesLink() {
			const collectivesPath = this.internalPath.startsWith(collectivesFolder)
				? this.internalPath.slice(collectivesFolder.length)
				: ''
			return generateUrl('/apps/collectives' + collectivesPath)
		},
	},

	watch: {
		path: function() {
			this.setPath(this.path)
		},
	},

	mounted() {
		this.setActive()
	},

	methods: {
		t,

		setPath(path) {
			this.internalPath = path
			this.setActive()
		},

		setActive() {
			this.active = collectivesFolder && collectivesFolder !== '/' && this.internalPath.startsWith(collectivesFolder)
		},
	},
}
</script>

<style lang="scss" scoped>
.filelist-collectives-wrapper {
	padding: 28px 30px 0 50px;
	margin-bottom: 20px;
	display: flex;
	overflow: hidden;
	flex-wrap: wrap;
	min-width: 0;

	.infobox {
		display: flex;
		align-items: center;
		justify-content: flex-start;

		background-color: var(--color-background-hover);
		border-inline-start-color: var(--color-primary-element);
		border-radius: var(--border-radius);
		padding: 1em;
		padding-inline-start: 0.5em;
		border-inline-start-width: 0.3em;
		border-inline-start-style: solid;
		margin-bottom: 0.5em;

		.content {
			display: flex;
			align-items: center;
			justify-content: flex-start;

			margin-inline-start: 1em;
			margin-bottom: 0;

			.button {
				margin-inline-start: 12px;
			}
		}
	}
}
</style>
