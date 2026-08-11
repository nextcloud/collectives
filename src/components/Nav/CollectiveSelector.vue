<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="selectorEl" class="collective-selector">
		<NcPopover
			class="collective-selector-popover-trigger"
			:shown="showSelector"
			:triggers="[]"
			placement="bottom-start"
			container="#app-navigation-vue"
			popoverBaseClass="collective-selector-popover"
			@update:shown="showSelector = $event">
			<template #trigger="{ attrs }">
				<div class="collective-selector-trigger-row">
					<component
						:is="currentCollective ? 'router-link' : 'div'"
						:to="currentCollective ? currentCollectivePath : undefined"
						:role="currentCollective ? undefined : 'button'"
						:tabindex="currentCollective ? undefined : 0"
						class="collective-selector-trigger"
						@click="onTriggerClick"
						@keydown.enter="onTriggerKeydown"
						@keydown.space="onTriggerKeydown">
						<span class="collective-selector-icon">
							<template v-if="currentCollective?.emoji">
								{{ currentCollective.emoji }}
							</template>
							<CollectivesIcon v-else :size="20" />
						</span>
						<span class="collective-selector-label">
							{{ currentCollective ? currentCollective.name : t('collectives', 'Select a collective') }}
						</span>
					</component>
					<button
						type="button"
						class="collective-selector-chevron-button"
						v-bind="attrs"
						:aria-label="t('collectives', 'Select a collective')"
						@click="onChevronClick">
						<ChevronDownIcon
							:size="20"
							class="collective-selector-chevron"
							:class="{ 'collective-selector-chevron--open': showSelector }" />
					</button>
				</div>
			</template>
			<template #default>
				<div class="collective-selector-popover-content">
					<template v-if="loading('collectives')">
						<NcAppNavigationCaption :name="t('collectives', 'Select a collective')" />
						<SkeletonLoading type="items" :count="3" />
					</template>
					<template v-else>
						<NcAppNavigationCaption :name="t('collectives', 'Select a collective')" />
						<ul class="collective-selector-list" @click="showSelector = false">
							<CollectiveListItem
								v-for="collective in sortedCollectives"
								v-show="!collective.deleted"
								:key="collective.id"
								:collective />
						</ul>
						<template v-if="!isPublic">
							<hr class="collective-selector-divider">
							<NcAppNavigationNew
								:text="t('collectives', 'New collective')"
								:disabled="!networkOnline"
								variant="tertiary"
								class="new-collective-button"
								@click="onNewCollective">
								<template #icon>
									<PlusIcon />
								</template>
							</NcAppNavigationNew>
						</template>
						<CollectivesTrash
							v-if="displayTrash"
							:networkOnline
							@restoreCollective="onRestoreCollective"
							@deleteCollective="onDeleteCollective" />
						<CollectivesGlobalSettings v-if="!isPublic" :networkOnline class="collective-selector-global-settings" />
					</template>
				</div>
			</template>
		</NcPopover>
		<NcActions
			v-if="currentCollective"
			forceMenu
			class="collective-selector-actions"
			container="#app-navigation-vue"
			:aria-label="t('collectives', 'Collective actions')">
			<NcActionCollectiveActions
				:collective="currentCollective"
				:networkOnline />
		</NcActions>
		<CollectiveMembersModal
			v-if="showCollectiveMembersModal"
			:collective="membersCollective"
			@close="onCloseCollectiveMembersModal" />
		<TemplatesDialog v-if="templatesCollectiveId" />
	</div>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { useElementSize } from '@vueuse/core'
import { mapActions, mapState } from 'pinia'
import { ref, watch } from 'vue'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import NcActionCollectiveActions from '../Collective/NcActionCollectiveActions.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import SkeletonLoading from '../SkeletonLoading.vue'
import CollectiveListItem from './CollectiveListItem.vue'
import CollectiveMembersModal from './CollectiveMembersModal.vue'
import CollectivesGlobalSettings from './CollectivesGlobalSettings.vue'
import CollectivesTrash from './CollectivesTrash.vue'
import TemplatesDialog from './TemplatesDialog.vue'
import { useNetworkState } from '../../composables/useNetworkState.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'
import displayError from '../../util/displayError.js'

export default {
	name: 'CollectiveSelector',

	components: {
		ChevronDownIcon,
		CollectiveListItem,
		CollectiveMembersModal,
		CollectivesGlobalSettings,
		CollectivesIcon,
		CollectivesTrash,
		NcActionCollectiveActions,
		NcActions,
		NcAppNavigationCaption,
		NcAppNavigationNew,
		NcPopover,
		PlusIcon,
		SkeletonLoading,
		TemplatesDialog,
	},

	setup() {
		const { networkOnline } = useNetworkState()

		// Track the selector's own width so the popover can match the sidebar's width
		const selectorEl = ref()
		const { width: selectorWidth } = useElementSize(selectorEl)
		watch(selectorWidth, (width) => {
			if (width > 0) {
				document.documentElement.style.setProperty('--collective-selector-width', `${width}px`)
			}
		}, { immediate: true })

		return { networkOnline, selectorEl }
	},

	data() {
		return {
			showSelector: false,
		}
	},

	computed: {
		...mapState(useRootStore, ['isPublic', 'loading']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectivePath',
			'membersCollective',
			'sortedCollectives',
			'templatesCollectiveId',
		]),

		displayTrash() {
			return !this.isPublic
				&& !this.loading('collectives')
		},

		showCollectiveMembersModal() {
			return !!this.membersCollective
		},
	},

	methods: {
		t,

		...mapActions(useCollectivesStore, [
			'deleteCollective',
			'restoreCollective',
			'setMembersCollectiveId',
		]),

		onTriggerClick(event) {
			if (!this.currentCollective) {
				event.preventDefault()
				this.showSelector = !this.showSelector
				return
			}

			const isModifierClick = event.ctrlKey || event.metaKey || event.shiftKey
			const clickedLabel = event.target.closest('.collective-selector-label')

			if (clickedLabel || isModifierClick) {
				this.showSelector = false
				return
			}

			event.preventDefault()
			this.showSelector = !this.showSelector
		},

		onTriggerKeydown(event) {
			event.preventDefault()

			if (!this.currentCollective) {
				this.showSelector = !this.showSelector
				return
			}

			this.showSelector = false
			this.$router.push(this.currentCollectivePath)
		},

		onChevronClick() {
			this.showSelector = !this.showSelector
		},

		onRestoreCollective(collective) {
			return this.restoreCollective(collective)
				.catch(displayError('Could not restore collective from trash'))
		},

		onDeleteCollective(collective, circle) {
			return this.deleteCollective({ ...collective, circle })
				.catch(displayError('Could not delete collective from trash'))
		},

		onNewCollective() {
			emit('open-new-collective-modal')
		},

		onCloseCollectiveMembersModal() {
			this.setMembersCollectiveId(null)
		},
	},
}
</script>

<style lang="scss">
:root {
	// Consumed by PageList.vue's --page-list-height calc, since this
	// component is always rendered as its sibling above the page list.
	--collective-selector-height: calc(var(--default-clickable-area) + 8px);
}
</style>

<style lang="scss" scoped>
.collective-selector {
	display: flex;
	align-items: center;
	margin-top: 4px;
	justify-content: space-between;
	gap: 2px;
	min-height: var(--collective-selector-height);
	padding-inline: 4px;
}

.collective-selector-popover-trigger {
	flex-grow: 1;
	min-width: 0;
}

.collective-selector-trigger-row {
	display: flex;
	align-items: center;
	min-width: 0;
	padding-left: 4px;
	margin-top: 5px;
}

.collective-selector-trigger {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
	flex-grow: 1;
	height: var(--default-clickable-area);
	padding-inline: 0;
	border-radius: var(--border-radius-large);
	color: var(--color-main-text);
	text-decoration: none;

	&:hover, &:focus, &:active {
		background-color: var(--color-background-hover);
	}
}

.collective-selector-chevron-button {
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	width: var(--default-clickable-area);
	height: var(--default-clickable-area);
	padding: 0;
	border: none;
	border-radius: var(--border-radius-large);
	background-color: transparent;
	color: var(--color-main-text);
	cursor: pointer;

	&:hover, &:focus, &:active {
		background-color: var(--color-background-hover);
	}
}

.collective-selector-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	font-size: 20px;
}

.collective-selector-label {
	overflow: hidden;
	flex-grow: 1;
	font-weight: bold;
	font-size: 1.2em;
	white-space: nowrap;
	text-overflow: ellipsis;
}

.collective-selector-chevron {
	flex-shrink: 0;
	transition: transform var(--animation-slow);

	&--open {
		transform: rotate(180deg);
	}
}

.collective-selector-actions {
	flex-shrink: 0;
}

.collective-selector-divider {
	border: none;
	border-top: 1px solid var(--color-border);
	margin: 4px 4px 8px;
}

.collective-selector-list {
	:deep(.app-navigation-entry) {
		margin-inline: 4px;
		width: calc(100% - 8px);
	}

	:deep(.app-navigation-entry-icon) {
		flex-basis: auto;
		width: auto;
		justify-content: flex-start;
	}

	:deep(.app-navigation-entry-link),
	:deep(.app-navigation-entry__name) {
		font-weight: normal !important;
	}

	:deep(.app-navigation-entry:focus-within) {
		background-color: transparent !important;
	}

	:deep(.app-navigation-entry:has(:focus-visible)) {
		background-color: var(--color-background-hover) !important;
	}
}

.collective-selector-popover-content {
	padding-bottom: 4px;

	:deep(.new-collective-button) {
		padding: 0 2px 4px 4px;
	}

	:deep(.new-collective-button .button-vue) {
		padding: 0;
		width: calc(100% - 3px);
	}

	:deep(.new-collective-button .button-vue__wrapper) {
		justify-content: start;
	}

	:deep(.new-collective-button .button-vue__text) {
		font-weight: normal;
	}
}

.collective-selector-global-settings {
	padding-top: 0 !important;
}

:deep(.collective-selector-global-settings > div:first-child) {
	margin-inline: 0;
}

:deep(.collective-selector-global-settings .button-vue) {
	padding-inline: 0 !important;
}
</style>

<style lang="scss">
.collective-selector-popover {
	width: calc(var(--collective-selector-width, 300px) - 32px);
}
</style>
