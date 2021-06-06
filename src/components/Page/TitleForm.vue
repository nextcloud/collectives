<template>
	<form @submit.prevent="renamePage">
		<input v-if="landingPage"
			class="title"
			type="text"
			disabled
			:value="collectiveTitle">
		<input v-else
			ref="title"
			v-model="newTitle"
			class="title"
			:placeholder="t('collectives', 'Title')"
			type="text"
			:disabled="!savePossible">
	</form>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import { RENAME_PAGE, GET_PAGES } from '../../store/actions'
import { CLEAR_UPDATED_PAGE } from '../../store/mutations'

export default {
	name: 'TitleForm',

	data() {
		return {
			newTitle: '',
		}
	},

	computed: {
		...mapGetters([
			'currentPage',
			'currentCollective',
			'indexPage',
			'landingPage',
			'pageParam',
			'updatedPagePath',
			'loading',
		]),

		collectiveTitle() {
			const { emoji, name } = this.currentCollective
			return emoji ? `${emoji} ${name}` : name
		},

		/**
		 * Return true if a page is selected and its title is not empty
		 * @returns {boolean}
		 */
		savePossible() {
			return this.currentPage && this.currentPage.title !== ''
		},

	},

	watch: {
		'currentPage.id'() {
			this.initTitleEntry()
		},
	},

	mounted() {
		this.initTitleEntry()
	},

	methods: {
		...mapMutations(['done', 'load', 'toggle']),

		initTitleEntry() {
			if (this.loading('newPage')) {
				this.newTitle = ''
				this.$nextTick(this.focusTitle)
				this.done('newPage')
			} else {
				this.newTitle = this.currentPage.title
			}
		},

		focusTitle() {
			this.$refs.title.focus()
			this.$emit('typing')
		},

		/**
		 * Rename currentPage on the server
		 */
		async renamePage() {
			this.$emit('done')
			if (!this.newTitle || this.newTitle === this.currentPage.title) {
				return
			}
			try {
				await this.$store.dispatch(RENAME_PAGE, this.newTitle)
				this.$router.push(this.updatedPagePath)
				this.$store.commit(CLEAR_UPDATED_PAGE)
				if (this.currentPage.size === 0) {
					this.$emit('edit')
				}
				this.$store.dispatch(GET_PAGES)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename the page'))
			}
		},

	},
}
</script>
