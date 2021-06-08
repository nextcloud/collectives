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
			:class="{ inactive: !focussed && !titleChanged }"
			style="height: 43px;"
			:placeholder="t('collectives', 'Title')"
			type="text">
		<input type="submit"
			value=""
			class="icon-confirm"
			:class="{ inactive: !focussed && !titleChanged }"
			style="height: 43px;">
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
			focussed: false,
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

		titleChanged() {
			return this.newTitle && this.newTitle !== this.currentPage.title
		},

	},

	watch: {
		'currentPage.id'() {
			this.initTitleEntry()
		},
		'titleChanged'(current, previous) {
			if (current && !previous) {
				this.$emit('typing')
			}
		},

	},

	mounted() {
		this.initTitleEntry()
	},

	methods: {
		...mapMutations(['done', 'load', 'toggle']),

		initTitleEntry() {
			this.focussed = false
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
			this.focussed = true
			this.$emit('typing')
		},

		/**
		 * Rename currentPage on the server
		 */
		async renamePage() {
			if (!this.titleChanged) {
				return
			}
			this.focussed = false
			this.$emit('done')
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

<style scoped>

form {
	flex: auto;
	display: flex;
}

input[type=text] {
	font-size: 35px;
	border-right: none;
	color: var(--color-main-text);
	width: 100%;
	height: 43px;
	opacity: 0.8;
	flex: auto;
}

input[type=submit] {
	flex: initial;
	margin-right: 20px;
}

input.inactive {
	border-color: transparent;
}

input.inactive.icon-confirm {
	background-image: none;
}

button, input {
	margin-top: 0px;
}

</style>
