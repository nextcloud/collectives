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
			:noCloseOnClickOutside="pickingFolder"
			@update:shown="showSelector = $event">
			<template #trigger="{ attrs }">
				<div class="collective-selector-trigger-row">
					<NcButton
						class="collective-selector-trigger"
						v-bind="attrs"
						variant="tertiary"
						alignment="start"
						@click="onTriggerClick">
						<template #icon>
							<span class="collective-selector-icon">
								<template v-if="currentCollective?.emoji">
									{{ currentCollective.emoji }}
								</template>
								<CollectivesIcon v-else :size="20" />
							</span>
						</template>
						<span class="collective-selector-label">
							{{ currentCollective ? currentCollective.name : t('collectives', 'Select a collective') }}
						</span>
						<ChevronDownIcon
							:size="20"
							class="collective-selector-chevron"
							:class="{ 'collective-selector-chevron--open': showSelector }" />
					</NcButton>
					<NcActions
						v-if="currentCollective"
						forceMenu
						class="collective-selector-actions"
						:aria-label="t('collectives', 'Collective actions')">
						<NcActionCollectiveActions
							v-model:submenu="collectiveSubmenu"
							:collective="currentCollective"
							:networkOnline />
					</NcActions>
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
							<CollectivesTrash
								:networkOnline
								@restoreCollective="onRestoreCollective"
								@deleteCollective="onDeleteCollective" />
							<CollectivesGlobalSettings
								:networkOnline
								class="collective-selector-global-settings"
								@update:pickingFolder="pickingFolder = $event" />
						</template>
					</template>
				</div>
			</template>
		</NcPopover>
		<CollectiveMembersModal
			v-if="showCollectiveMembersModal"
			:collective="membersCollective"
			@close="onCloseCollectiveMembersModal" />
		<CollectivePublishModal
			v-if="showCollectivePublishModal"
			:collective="publishCollective"
			@close="onCloseCollectivePublishModal" />
		<TemplatesDialog v-if="templatesCollectiveId" />
	</div>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
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
import CollectivePublishModal from './CollectivePublishModal.vue'
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
		CollectivePublishModal,
		CollectivesGlobalSettings,
		CollectivesIcon,
		CollectivesTrash,
		NcActionCollectiveActions,
		NcActions,
		NcAppNavigationCaption,
		NcAppNavigationNew,
		NcPopover,
		NcButton,
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
			collectiveSubmenu: null,
			pickingFolder: false,
		}
	},

	computed: {
		...mapState(useRootStore, ['isPublic', 'loading']),
		...mapState(useCollectivesStore, [
			'collectivePath',
			'currentCollective',
			'membersCollective',
			'publishCollective',
			'sortedCollectives',
			'templatesCollectiveId',
		]),

		showCollectiveMembersModal() {
			return !!this.membersCollective
		},

		showCollectivePublishModal() {
			return !!this.publishCollective
		},
	},

	methods: {
		t,

		...mapActions(useCollectivesStore, [
			'deleteCollective',
			'restoreCollective',
			'setMembersCollectiveId',
			'setPublishCollectiveId',
		]),

		onTriggerClick() {
			this.showSelector = !this.showSelector
		},

		onRestoreCollective(collective) {
			return this.restoreCollective(collective)
				.then(() => {
					this.showSelector = false
					this.$router.push(this.collectivePath(collective))
				})
				.catch(displayError('Could not restore collective from trash'))
		},

		onDeleteCollective(collective, circle) {
			return this.deleteCollective({ ...collective, circle })
				.catch(displayError('Could not delete collective from trash'))
		},

		onNewCollective() {
			this.showSelector = false
			emit('open-new-collective-modal')
		},

		onCloseCollectiveMembersModal() {
			this.setMembersCollectiveId(null)
		},

		onCloseCollectivePublishModal() {
			this.setPublishCollectiveId(null)
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

.app-navigation__list {
	padding: 0 !important
}

.collective-selector-popover {
	width: calc(var(--collective-selector-width, 300px) - 32px);
}
</style>

<style lang="scss" scoped>
.collective-selector {
	display: flex;
	align-items: center;
	gap: 2px;
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
}

.collective-selector-trigger {
	flex-grow: 1;
	min-width: 0;
	--button-radius: var(--border-radius-large);

	:deep(.button-vue__text) {
		display: flex;
		align-items: center;
		gap: 8px;
		flex-grow: 1;
		min-width: 0;
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
	min-width: 0;
	font-weight: bold;
	font-size: 1.2em;
	text-align: start;
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
		margin: 2px 4px;
		width: calc(100% - 8px);
	}

	:deep(.app-navigation-entry-link),
	:deep(.app-navigation-entry__name) {
		font-weight: normal !important;
	}

	:deep(.app-navigation-entry:has(:focus-visible)) {
		background-color: var(--color-background-hover) !important;
	}
}

.collective-selector-popover-content {
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
