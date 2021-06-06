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
import { emit } from '@nextcloud/event-bus'
import { mapGetters, mapMutations } from 'vuex'
import { GET_PAGES } from '../store/actions'
import { SELECT_VERSION } from '../store/mutations'
import displayError from '../util/displayError'
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
			'currentCollective',
			'currentPage',
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
		'currentCollective.id'() {
			this.initCollective()
		},
		'currentPage.id'() {
			this.editToggle = EditState.Unset
			this.$store.commit(SELECT_VERSION, null)
		},
	},

	mounted() {
		this.initCollective()
	},

	methods: {
		...mapMutations(['show']),

		initCollective() {
			if (this.currentCollective) {
				this.getPages()
				this.closeNav()
				this.show('details')
			} else {
				this.openNav()
			}
		},

		/**
		 * Get list of all pages
		 * @returns {Promise}
		 */
		getPages() {
			return this.$store.dispatch(GET_PAGES)
				.catch(displayError('Could not fetch pages'))
		},

		closeNav() {
			emit('toggle-navigation', { open: false })
		},

		openNav() {
			emit('toggle-navigation', { open: true })
		},

	},

}
</script>
