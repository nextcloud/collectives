<template>
	<div v-if="active" class="filelist-collectives-wrapper">
		<div class="infobox">
			<InformationIcon fill-color="var(--color-primary-element)" />

			<div class="content">
				{{ t('collectives', 'The content of this folder is best viewed in the Collectives app.') }}

				<a :href="collectivesLink">
					<NcButton :aria-label="t('collectives', 'Open in Collectives')"
						type="primary"
						class="button">
						{{ t('collectives', 'Open in Collectives') }}
					</NcButton>
				</a>
			</div>
		</div>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
// As it's the only nextcloud-vue component used in collectives-files.js, import it separately to keep asset small
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import InformationIcon from 'vue-material-design-icons/Information.vue'

export default {
	name: 'FileListInfo',

	components: {
		InformationIcon,
		NcButton,
	},

	props: {
		collectivesFolder: {
			type: String,
			required: true,
		},
		path: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			active: false,
		}
	},

	computed: {
		collectivesLink() {
			const collectivesPath = this.path.startsWith(this.collectivesFolder)
				? this.path.slice(this.collectivesFolder.length)
				: ''
			return generateUrl('/apps/collectives' + collectivesPath)
		},
	},

	watch: {
		'path'() {
			this.setActive()
		},
	},

	mounted() {
		this.setActive()
	},

	methods: {
		setActive() {
			this.active = this.collectivesFolder && this.collectivesFolder !== '/' && this.path.startsWith(this.collectivesFolder)
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
		border-left-color: var(--color-primary-element);
		border-radius: var(--border-radius);
		padding: 1em;
		padding-left: 0.5em;
		border-left-width: 0.3em;
		border-left-style: solid;
		margin-bottom: 0.5em;

		.content {
			display: flex;
			align-items: center;
			justify-content: flex-start;

			margin-left: 1em;
			margin-bottom: 0;

			.button {
				margin-left: 12px;
			}
		}
	}
}
</style>
