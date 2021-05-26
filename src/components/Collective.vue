<template>
	<div id="app-content-wrapper">
		<PagesList />
		<AppContentDetails v-if="currentPage">
			<Version v-if="version" />
			<Page v-else
				:edit="edit"
				@edit="edit = true"
				@toggleEdit="edit = !edit" />
		</AppContentDetails>
	</div>
</template>

<script>

import { mapGetters } from 'vuex'
import AppContentDetails from '@nextcloud/vue/dist/Components/AppContentDetails'
import Page from '../components/Page'
import PagesList from '../components/PagesList'
import Version from '../components/Version'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'Collective',

	components: {
		AppContentDetails,
		Page,
		PagesList,
		Version,
	},

	data() {
		return {
			editToggle: EditState.Unset,
		}
	},

	computed: {
		...mapGetters([
			'currentPage',
			'pageParam',
			'version',
		]),

		edit: {
			get() {
				return this.editToggle === EditState.Edit
			},
			set(val) {
				this.editToggle = val ? EditState.Edit : EditState.Read
			},
		},
	},

	watch: {
		'currentPage.id'() {
			this.editToggle = EditState.Unset
		},
	},

}
</script>
