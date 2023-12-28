<template>
	<li class="sharing-entry sharing-entry__link">
		<NcAvatar :is-no-user="true"
			icon-class="avatar-link-share icon-public-white"
			class="sharing-entry__avatar" />
		<div class="sharing-entry__summary">
			<div class="sharing-entry__desc">
				<span class="sharing-entry__title" :title="title">
					{{ title }}
				</span>
				<div v-if="share"
					ref="quickShareDropdownContainer"
					:class="{ 'active': showDropdown, 'share-select': true }">
					<span :id="dropdownId"
						class="trigger-text"
						:aria-expanded="showDropdown"
						:aria-haspopup="true"
						aria-label="t('collectives', 'Quick share options dropdown')"
						@click="toggleDropdown">
						{{ selectedDropdownOption }}
						<TriangleSmallDownIcon :size="15" />
					</span>
					<div v-if="showDropdown"
						ref="quickShareDropdown"
						class="share-select-dropdown"
						:aria-labelledby="dropdownId"
						tabindex="0"
						@keydown.down="handleArrowDown"
						@keydown.up="handleArrowUp"
						@keydown.esc="closeDropdown">
						<button v-for="option in dropdownOptions"
							:key="option"
							class="dropdown-item"
							:class="{ 'selected': option === selectedDropdownOption }"
							:aria-selected="option === selectedDropdownOption"
							@click="selectOption(option)">
							{{ option }}
						</button>
					</div>
				</div>
			</div>

			<!-- clipboard -->
			<NcActions v-if="share && share.token" ref="copyButton" class="sharing-entry__copy">
				<NcActionButton :aria-label="copyLinkTooltip"
					@click.prevent="copyLink">
					<template #icon>
						<CheckIcon v-if="copySuccess" :size="20" />
						<NcLoadingIcon v-else-if="copyLoading" :size="20" />
						<ContentCopyIcon v-else :size="20" />
					</template>
					{{ copyLinkTooltip }}
				</NcActionButton>
			</NcActions>
		</div>

		<!-- actions -->
		<NcActions v-if="!loading"
			class="sharing-entry__actions"
			:aria-label="actionsTooltip"
			menu-align="right"
			:open.sync="open">
			<template v-if="share">
				<NcActionButton class="new-share-link" @click.prevent.stop="onNewShare">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('collectives', 'Add another link') }}
				</NcActionButton>

				<NcActionButton class="unshare-button" @click.prevent="onDelete">
					<template #icon>
						<CloseIcon :size="20" />
					</template>
					{{ t('collectives', 'Unshare') }}
				</NcActionButton>
			</template>

			<!-- Create new share -->
			<NcActionButton v-else
				class="new-share-link"
				:aria-label="t('collectives', 'Create a new share link')"
				@click.prevent.stop="onNewShare">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('collectives', 'Create a new share link') }}
			</NcActionButton>
		</NcActions>

		<!-- loading indicator to replace the menu -->
		<NcLoadingIcon v-else :size="20" class="sharing-entry__loading" />
	</li>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { createFocusTrap } from 'focus-trap'
import { NcAvatar, NcActionButton, NcActions, NcLoadingIcon } from '@nextcloud/vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import TriangleSmallDownIcon from 'vue-material-design-icons/TriangleSmallDown.vue'

import CopyToClipboardMixin from '../../mixins/CopyToClipboardMixin.js'
import {
	CREATE_SHARE,
	UPDATE_SHARE,
	DELETE_SHARE,
} from '../../store/actions.js'

export default {
	name: 'SharingEntryLink',

	components: {
		CheckIcon,
		CloseIcon,
		ContentCopyIcon,
		NcActionButton,
		NcActions,
		NcLoadingIcon,
		NcAvatar,
		PlusIcon,
		TriangleSmallDownIcon,
	},

	mixins: [
		CopyToClipboardMixin,
	],

	props: {
		index: {
			type: Number,
			default: null,
		},
		share: {
			type: Object,
			default: () => {},
		},
	},

	data() {
		return {
			showDropdown: false,
			loading: false,
			open: false,
			focusTrap: null,
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentPage',
			'isLandingPage',
		]),

		title() {
			return this.index > 1
				? t('collectives', 'Share link ({index})', { index: this.index })
				: t('collectives', 'Share link')
		},

		dropdownId() {
			// Generate a unique ID for ARIA attributes
			return `dropdown-${Math.random().toString(36).substring(2, 9)}`
		},

		viewOnlyText() {
			return t('collectives', 'View only')
		},

		canEditText() {
			return t('collectives', 'Can edit')
		},

		dropdownOptions() {
			return [
				this.viewOnlyText,
				this.canEditText,
			]
		},

		selectedDropdownOption() {
			return this.share?.editable
				? this.canEditText
				: this.viewOnlyText
		},

		copyLinkTooltip() {
			if (this.copied) {
				if (this.copySuccess) {
					return ''
				}
				return t('collectives', 'Cannot copy, please copy the link manually')
			}
			return t('collectives', 'Copy public link of {title} to clipboard', { title: this.title })
		},

		isPageShare() {
			return !this.isLandingPage
		},

		shareUrl() {
			return this.share
				? window.location.origin + generateUrl(`/apps/collectives/p/${this.share.token}/${encodeURIComponent(this.currentCollective.name)}`)
				: null
		},

		actionsTooltip() {
			return t('collectives', 'Actions for "{title}"', { title: this.title })
		},
	},

	mounted() {
		window.addEventListener('click', this.handleClickOutside)
	},

	beforeDestroy() {
		// Remove the global click event listener to prevent memory leaks
		window.removeEventListener('click', this.handleClickOutside)
	},

	methods: {
		...mapActions({
			dispatchCreateShare: CREATE_SHARE,
			dispatchDeleteShare: DELETE_SHARE,
			dispatchUpdateShare: UPDATE_SHARE,
		}),

		handleClickOutside(event) {
			const dropdownContainer = this.$refs.quickShareDropdownContainer

			if (dropdownContainer && !dropdownContainer.contains(event.target)) {
				this.showDropdown = false
			}
		},

		handleArrowUp() {
			const currentElement = document.activeElement
			let previousElement = currentElement.previousElementSibling
			if (!previousElement) {
				previousElement = this.$refs.quickShareDropdown.lastElementChild
			}
			previousElement.focus()
		},

		handleArrowDown() {
			const currentElement = document.activeElement
			let previousElement = currentElement.nextElementSibling
			if (!previousElement) {
				previousElement = this.$refs.quickShareDropdown.firstElementChild
			}
			previousElement.focus()
		},

		selectOption(option) {
			const share = { ...this.share }
			if (option === this.viewOnlyText && this.share.editable) {
				share.editable = false
				this.onUpdate(share)
			} else if (option === this.canEditText && !this.share.editable) {
				share.editable = true
				this.onUpdate(share)
			}
			this.closeDropdown()
		},

		toggleDropdown() {
			this.showDropdown = !this.showDropdown
			if (this.showDropdown) {
				this.$nextTick(() => {
					this.useFocusTrap()
				})
			} else {
				this.clearFocusTrap()
			}
		},

		closeDropdown() {
			this.showDropdown = false
		},

		useFocusTrap() {
			// Create global stack if undefined
			// Use in with trapStack to avoid conflicting traps
			Object.assign(window, { _nc_focus_trap: window._nc_focus_trap || [] })
			const dropdownElement = this.$refs.quickShareDropdown
			this.focusTrap = createFocusTrap(dropdownElement, {
				allowOutsideClick: true,
				trapStack: window._nc_focus_trap,
			})

			this.focusTrap.activate()
		},

		clearFocusTrap() {
			this.focusTrap?.deactivate()
			this.focusTrap = null
		},

		async copyLink() {
			await this.copyToClipboard(this.shareUrl)
		},

		async onNewShare() {
			if (this.loading) {
				return
			}

			try {
				this.loading = true
				this.open = false
				await this.dispatchCreateShare({
					collectiveId: this.currentCollective.id,
					pageId: this.isPageShare ? this.currentPage.id : 0,
				})
				const message = this.isPageShare
					? t('collectives', 'Page "{name}" has been shared', { name: this.currentPage.title })
					: t('collectives', 'Collective "{name}" has been shared', { name: this.currentCollective.name })
				showSuccess(message)
			} catch (error) {
				const message = this.isPageShare
					? t('collectives', 'Failed to share page "{name}"', { name: this.currentPage.title })
					: t('collectives', 'Failed to share collective "{name}"', { name: this.currentCollective.name })
				showError(message)
				console.error('Failed to create share', error)
				this.open = true
			} finally {
				this.loading = false
			}
		},

		async onUpdate(share) {
			if (this.loading) {
				return
			}

			try {
				this.loading = true
				this.open = false
				await this.dispatchUpdateShare(share)
				const message = this.isPageShare
					? t('collectives', 'Share link of page "{name}" has been updated', { name: this.currentPage.title })
					: t('collectives', 'Share link of collective "{name}" has been updated', { name: this.currentCollective.name })
				showSuccess(message)
			} catch (error) {
				console.error('Failed to update share link', error)
				this.open = true
			} finally {
				this.loading = false
			}
		},

		async onDelete() {
			if (this.loading) {
				return
			}

			try {
				this.loading = true
				this.open = false
				await this.dispatchDeleteShare(this.share)
				const message = this.isPageShare
					? t('collectives', 'Page "{name}" has been unshared', { name: this.currentPage.title })
					: t('collectives', 'Collective "{name}" has been unshared', { name: this.currentCollective.name })
				showSuccess(message)
			} catch (error) {
				console.error('Failed to unshare', error)
				this.open = true
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.sharing-entry {
	display: flex;
	align-items: center;
	min-height: 44px;

	&__summary {
		padding: 8px;
		padding-left: 10px;
		display: flex;
		justify-content: space-between;
		flex: 1 0;
		min-width: 0;
	}

	&__desc {
		display: flex;
		flex-direction: column;
		line-height: 1.2em;

		&__title {
			text-overflow: ellipsis;
			overflow: hidden;
			white-space: nowrap;
		}
	}

	&__loading {
		width: 44px;
		height: 44px;
	}

	:deep(.avatar-link-share) {
		background-color: var(--color-primary-element);
	}
}

.share-select {
	position: relative;
	cursor: pointer;

	.trigger-text {
		display: flex;
		flex-direction: row;
		align-items: center;
		font-size: 12.5px;
		gap: 2px;
		color: var(--color-primary-element);
	}

	.share-select-dropdown {
		position: absolute;
		display: flex;
		flex-direction: column;
		top: 100%;
		left: 0;
		background-color: var(--color-main-background);
		border-radius: 8px;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
		border: 1px solid var(--color-border);
		padding: 4px 0;
		z-index: 1;

		max-height: 0;
		overflow: hidden;
		transition: max-height 0.3s ease;

		.dropdown-item {
			padding: 8px;
			font-size: 12px;
			background: none;
			border: none;
			border-radius: 0;
			font: inherit;
			cursor: pointer;
			color: inherit;
			outline: none;
			width: 100%;
			white-space: nowrap;
			text-align: left;

			&:hover {
				background-color: var(--color-background-dark);
			}

			&.selected {
				background-color: var(--color-background-dark);
			}
		}
	}

	&.active .share-select-dropdown {
		max-height: 200px;
	}
}
</style>
