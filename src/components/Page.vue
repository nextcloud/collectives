<template>
	<div>
		<h1 id="titleform" class="page-title">
			<form @submit.prevent="renamePage(); startEdit()">
				<input v-if="landingPage"
					class="title"
					type="text"
					disabled
					:value="collectiveTitle">
				<input v-else-if="isTemplatePage"
					class="title"
					type="text"
					disabled
					:value="t('collectives', 'Template')">
				<input v-else
					ref="title"
					v-model="newTitle"
					class="title"
					:placeholder="t('collectives', 'Title')"
					type="text"
					:disabled="!currentCollectiveCanEdit"
					@blur="renamePageOnBlur();">
			</form>
			<button v-if="currentCollectiveCanEdit"
				class="edit-button primary"
				:title="edit ? t('collectives', 'Stop editing') : t('collectives', 'Start editing')"
				@click="edit ? stopEdit() : startEdit()">
				<span class="icon icon-white"
					:class="`icon-${toggleIcon}`" />
				{{ edit && !waitForEdit ? t('collectives', 'Done') : t('collectives', 'Edit') }}
			</button>
			<PageActions v-if="currentCollectiveCanEdit" />
			<Actions v-show="!showing('sidebar')">
				<ActionButton icon="icon-menu-sidebar"
					:close-after-click="true"
					@click="toggle('sidebar')" />
			</Actions>
		</h1>
		<div v-if="readOnly" id="text-container" :key="'text-' + currentPage.id">
			<RichText :key="`show-${currentPage.id}`"
				:as-placeholder="preview && edit"
				:timestamp="currentPage.timestamp"
				@empty="emptyPreview"
				@loading="waitingFor.push('preview')"
				@ready="ready('preview')" />
			<button v-if="!('preview' in waitingFor) && hasSubpages"
				href="#"
				class="load-more"
				@click="toggle('subpages')">
				{{ showing('subpages')
					? t('collectives', 'Hide all subpages')
					: t('collectives', 'Show all subpages')
				}}
			</button>
			<Subpages v-if="showing('subpages')"
				:page-id="currentPage.id"
				@loading="waitingFor.push('subpages')"
				@ready="ready('subpages')" />
		</div>
		<Editor v-show="!readOnly"
			:key="`edit-${currentPage.id}-${reloadCounter}`"
			ref="editor"
			@ready="hidePreview" />
	</div>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Editor from './Page/Editor'
import RichText from './Page/RichText'
import PageActions from './Page/PageActions'
import Subpages from './Page/Subpages'
import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import {
	RENAME_PAGE,
	TOUCH_PAGE,
	GET_PAGES,
	GET_VERSIONS,
} from '../store/actions'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'Page',

	components: {
		ActionButton,
		Actions,
		Editor,
		RichText,
		PageActions,
		Subpages,
	},

	data() {
		return {
			previousSaveTimestamp: null,
			preview: true,
			previewWasEmpty: false,
			newTitle: '',
			editToggle: EditState.Unset,
			reloadCounter: 0,
			waitingFor: [],
		}
	},

	computed: {
		...mapGetters([
			'isPublic',
			'currentPage',
			'currentPageFilePath',
			'currentCollective',
			'currentCollectiveCanEdit',
			'indexPage',
			'landingPage',
			'pageParam',
			'loading',
			'visibleSubpages',
			'showing',
			'isTemplatePage',
		]),

		collectiveTitle() {
			const { emoji, name } = this.currentCollective
			return emoji ? `${emoji} ${name}` : name
		},

		titleChanged() {
			return this.newTitle && this.newTitle !== this.currentPage.title
		},

		documentTitle() {
			const { filePath, title } = this.currentPage
			const parts = [
				this.currentCollective.name,
				t('collectives', 'Collectives'),
				'Nextcloud',
			]
			if (!this.landingPage) {
				if (this.indexPage) {
					parts.unshift(filePath || title)
				} else {
					parts.unshift(filePath ? filePath + '/' + title : title)
				}
			}
			return parts.join(' - ')
		},

		toggleIcon() {
			if (this.loading('pageUpdate') || this.waitForEdit) {
				return 'loading-small'
			} else {
				return this.edit ? 'checkmark' : 'rename'
			}
		},

		waitForEdit() {
			return this.preview && this.edit
		},

		readOnly() {
			return !this.currentCollectiveCanEdit || this.preview || !this.edit
		},

		edit: {
			get() {
				return this.editToggle === EditState.Edit
			},
			set(val) {
				this.editToggle = val ? EditState.Edit : EditState.Read
			},
		},

		hasSubpages() {
			return this.visibleSubpages(this.currentPage.id).length
		},
	},

	watch: {
		'pageParam'() {
			this.initTitleEntry()
		},
		'currentPage.id'() {
			this.editToggle = EditState.Unset
			if (this.showing('print')) {
				this.show('subpages')
			} else {
				this.hide('subpages')
			}
		},
		'documentTitle'() {
			document.title = this.documentTitle
		},
	},

	mounted() {
		document.title = this.documentTitle
		this.initTitleEntry()
	},

	methods: {
		...mapMutations(['done', 'load', 'toggle', 'show', 'hide']),

		// this is a method so it does not get cached
		doc() {
			return this.wrapper().$data.document
		},

		// this is a method so it does not get cached
		wrapper() {
			return this.$refs.editor.$children[0].$children[0]
		},

		initTitleEntry() {
			if (this.loading('newPage')) {
				this.newTitle = ''
				// Older versions of text do not pass on the autofocus prop.
				// Only focus the title if the editor won't steal the focus.
				if (!this.wrapper().autofocus) {
					this.$nextTick(this.focusTitle)
				}
				this.done('newPage')
				return
			} else if (this.loading('newTemplate')) {
				// TODO: apparently focussing the editor doesn't work as expected
				this.$nextTick(this.focusEditor)
				this.done('newTemplate')
			}
			this.newTitle = this.currentPage.title
		},

		focusTitle() {
			this.$refs.title.focus()
		},

		focusEditor() {
			const editor = this.$el.querySelector('.ProseMirror')
			if (editor) {
				editor.focus()
			}
		},

		/**
		 * Set preview to false
		 */
		hidePreview() {
			this.preview = false
			if (this.edit) {
				if (this.doc()) {
					this.previousSaveTimestamp = this.doc().lastSavedVersionTime
				}
				this.focusEditor()
			}
		},

		emptyPreview() {
			this.previewWasEmpty = true
			if (this.editToggle === EditState.Unset) {
				this.startEdit()
			}
		},

		ready(part) {
			this.waitingFor.splice(this.waitingFor.indexOf(part), 1)
			if (!this.waitingFor.length && this.showing('print')) {
				this.$nextTick(() => {
					window.print()
					this.hide('print')
					this.hide('subpages')
				})
			}
		},

		startEdit() {
			if (this.doc()) {
				this.previousSaveTimestamp = this.doc().lastSavedVersionTime
			}
			this.edit = true
			this.$nextTick(this.focusEditor)
			this.hide('subpages')
		},

		async stopEdit() {
			this.renamePage()
			const wasDirty = this.wrapper().$data.dirty
			const changed = wasDirty
				|| this.doc().lastSavedVersionTime !== this.previousSaveTimestamp
			// if there is still no page content we remind the user
		    if (this.previewWasEmpty && !changed) {
				this.focusEditor()
				return
			}
			if (wasDirty) {
				this.load('pageUpdate')
				await this.wrapper().close()
				this.done('pageUpdate')
			}
			if (changed) {
				this.reloadCounter += 1
				this.previewWasEmpty = false
				this.$store.dispatch(TOUCH_PAGE)
				if (!this.isPublic) {
					this.$store.dispatch(GET_VERSIONS, this.currentPage.id)
				}
			}
			this.edit = false
		},

	    renamePageOnBlur() {
			// Cypress tests in ci trigger blur events randomly.
			if (window.Cypress) {
				return
			}
			return this.renamePage()
		},

		/**
		 * Rename currentPage on the server
		 */
		async renamePage() {
			if (!this.titleChanged) {
				return
			}
			try {
				await this.$store.dispatch(RENAME_PAGE, this.newTitle)
				// The resulting title may be different due to sanitizing
				this.newTitle = this.currentPage.title
				this.$store.dispatch(GET_PAGES)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename the page'))
			}
		},
	},
}
</script>

<style lang="scss">
#titleform form {
	flex: auto;
}

#text-container {
	display: block;
	width: 100%;
	max-width: 670px;
	left: 0;
	margin: 0 auto;
	background-color: var(--color-main-background);
	height: calc(100% - 50px);
	top: 50px;
}

#text-container .editor__content {
	border: 2px solid var(--color-main-background);
	border-radius: var(--border-radius);
}

.editor__content {
	border: 2px;
}

.page-title {
	padding: 8px 2px 2px 8px;
	position: relative;
	margin: auto;
	max-width: 670px;
	margin-bottom: -50px;
	display: flex;

	.title {
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.load-more {
	margin-top: 10px;
	margin-bottom: 10px;
}

// Leave space for page list toggle on small screens
// Editor/View: 670px, page list/details toggle: 44px
@media only screen and (max-width: 670px + 44px) {
	.page-title {
		padding: 8px 2px 2px 40px;
	}
}

#action-menu button {
	z-index: 1;
}

.edit-button {
	min-width: max-content;
	height: 44px;

	.icon {
		opacity: 1;
		margin-right: 8px;
	}
}

@media print {
	.edit-button, .action-item, .load-more {
		display: none !important;
	}
}
</style>
