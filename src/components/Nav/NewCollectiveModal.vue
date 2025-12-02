<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="dialogName"
		size="normal"
		@closing="onClose">
		<div v-if="state === 0" class="modal-collective-wrapper">
			<div class="modal-collective-name">
				<NcEmojiPicker :show-preview="true" @select="updateEmoji">
					<NcButton
						variant="tertiary"
						:aria-label="t('collectives', 'Select emoji for collective')"
						:title="t('collectives', 'Select emoji')"
						class="button-emoji"
						@click.prevent>
						{{ emoji }}
					</NcButton>
				</NcEmojiPicker>
				<NcTextField
					v-if="!pickCircle"
					ref="collectiveName"
					v-model="name"
					class="collective-name"
					:error="nameIsInvalid"
					:show-trailing-button="name !== ''"
					:label="t('collectives', 'Name of the collective')"
					@keypress.enter.prevent="advanceToMembers"
					@trailing-button-click="clearName" />
				<NcSelect
					v-else
					ref="circleSelector"
					v-model="circle"
					class="circle-selector"
					:append-to-body="false"
					:options="circles"
					:aria-label-combobox="t('collectives', 'Select an existing team')"
					:placeholder="t('collectives', 'Select a team…')" />
				<NcButton
					v-if="anyCircle && !pickCircle"
					:title="t('collectives', 'Select an existing team')"
					variant="tertiary"
					@click.stop.prevent="startSelectCircle">
					<template #icon>
						<TeamsIcon :size="16" />
					</template>
				</NcButton>
				<NcButton
					v-if="anyCircle && pickCircle"
					:title="t('collectives', 'Cancel selecting a team')"
					variant="tertiary"
					@click.stop.prevent="stopSelectCircle">
					<template #icon>
						<CloseIcon :size="16" />
					</template>
				</NcButton>
			</div>
			<div class="modal-collective-name-error-placeholder">
				<div v-if="nameError" class="modal-collective-name-error">
					<AlertCircleIcon :size="16" />
					<label for="collective-name" class="modal-collective-name-error-label">
						{{ nameError }}
					</label>
				</div>
			</div>

			<NcEmptyContent class="empty-content">
				<template #icon>
					<CollectivesIcon :size="20" />
				</template>
			</NcEmptyContent>
		</div>

		<div v-else-if="state === 1" class="modal-collective-wrapper">
			<div class="modal-collective-members">
				<MemberPicker
					:show-selection="true"
					:search-without-query="true"
					:current-user-is-admin="true"
					:selected-members="selectedMembers"
					:no-delete-members="noDeleteMembers"
					:on-click-searched="onClickSearched"
					@delete-from-selection="deleteMember" />
			</div>
		</div>

		<template #actions>
			<template v-if="state === 0">
				<NcButton :aria-label="t('collectives', 'Cancel')" @click="onClose">
					{{ t('collectives', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					:aria-label="t('collectives', 'Add members')"
					:disabled="!newCollectiveName || nameIsInvalid"
					class="modal-buttons-right"
					@click="advanceToMembers">
					{{ t('collectives', 'Add members') }}
				</NcButton>
			</template>
			<template v-else-if="state === 1">
				<NcButton :aria-label="t('collectives', 'Go back')" @click="state = 0">
					{{ t('collectives', 'Back') }}
				</NcButton>
				<NcButton
					variant="primary"
					:aria-label="createButtonString"
					:disabled="loading"
					class="modal-buttons-right"
					@click="onCreate">
					{{ createButtonString }}
				</NcButton>
			</template>
		</template>
	</NcDialog>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showInfo } from '@nextcloud/dialogs'
import { NcButton, NcDialog, NcEmojiPicker, NcEmptyContent, NcSelect, NcTextField } from '@nextcloud/vue'
import debounce from 'debounce'
import { mapActions, mapState } from 'pinia'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import TeamsIcon from '../Icon/TeamsIcon.vue'
import MemberPicker from '../Member/MemberPicker.vue'
import { autocompleteSourcesToCircleMemberTypes, circlesMemberTypes } from '../../constants.js'
import { useCirclesStore } from '../../stores/circles.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import displayError from '../../util/displayError.js'

export default {
	name: 'NewCollectiveModal',

	components: {
		AlertCircleIcon,
		TeamsIcon,
		CloseIcon,
		CollectivesIcon,
		MemberPicker,
		NcButton,
		NcDialog,
		NcEmojiPicker,
		NcEmptyContent,
		NcSelect,
		NcTextField,
	},

	data() {
		return {
			circle: null,
			emoji: null,
			loading: false,
			name: '',
			nameExists: '',
			pickCircle: false,
			currentUserId: getCurrentUser().uid,
			selectedMembers: {},
			noDeleteMembers: [],
			nameIsTooShort: false,
			nameIsTooLong: false,
			setNameIsTooShortDebounced: debounce(this.setNameIsTooShort, 500),
			setNameIsTooLongDebounced: debounce(this.setNameIsTooLong, 500),
			state: 0,
		}
	},

	computed: {
		...mapState(useCirclesStore, ['availableCircles']),
		...mapState(useCollectivesStore, [
			'collectiveChanged',
			'randomCollectiveEmoji',
			'updatedCollective',
			'updatedCollectivePath',
		]),

		circles() {
			return this.availableCircles.map((c) => c.sanitizedName)
		},

		anyCircle() {
			return this.circles.length > 0
		},

		dialogName() {
			return this.state === 0
				? t('collectives', 'New collective')
				: t('collectives', 'Add members to {name}', { name: this.newCollectiveName })
		},

		newCollectiveName() {
			return this.pickCircle ? this.circle : this.name
		},

		nameIsTaken() {
			return !!this.newCollectiveName && this.nameExists === this.newCollectiveName
		},

		nameIsInvalid() {
			return this.nameIsTooShort || this.nameIsTaken || this.nameIsTooLong
		},

		nameError() {
			if (this.nameIsTooShort) {
				return t('collectives', 'Name too short, requires at least three characters')
			} else if (this.nameIsTooLong) {
				return t('collectives', 'Name too long, must not exceed 127 characters')
			} else if (this.nameIsTaken) {
				return t('collectives', 'A collective/team with this name already exists')
			}
			return null
		},

		selectedMembersWithoutSelf() {
			return Object.keys(this.selectedMembers)
				.filter((key) => key !== `users-${this.currentUserId}`)
				.reduce((cur, key) => { return Object.assign(cur, { [key]: this.selectedMembers[key] }) }, {})
		},

		hasSelectedMembersWithoutSelf() {
			return Object.keys(this.selectedMembersWithoutSelf).length !== 0
		},

		createButtonString() {
			return this.hasSelectedMembersWithoutSelf
				? t('collectives', 'Create')
				: t('collectives', 'Create without members')
		},
	},

	watch: {
		newCollectiveName(val) {
			if (val && this.newCollectiveName.length < 3) {
				this.setNameIsTooShortDebounced()
				this.setNameIsTooLongDebounced.clear()
				this.nameIsTooShort = true
				this.nameIsTooLong = false
			} else if (this.newCollectiveName.length > 127) {
				this.setNameIsTooLongDebounced()
				this.setNameIsTooShortDebounced.clear()
				this.nameIsTooLong = true
				this.nameIsTooShort = false
			} else {
				this.setNameIsTooShortDebounced.clear()
				this.setNameIsTooLongDebounced.clear()
				this.nameIsTooShort = false
				this.nameIsTooLong = false
			}
		},

	},

	mounted() {
		this.selectedMembers[`users-${this.currentUserId}`] = {
			icon: 'icon-user',
			id: this.currentUserId,
			label: getCurrentUser().displayName,
			source: 'users',
		}
		this.noDeleteMembers = [`users-${this.currentUserId}`]
		this.emoji = this.randomCollectiveEmoji()
		this.getCircles()
			.catch(displayError('Could not get list of teams'))
		this.$nextTick(() => {
			this.$refs.collectiveName.$el.getElementsByTagName('input')[0]?.focus()
		})
	},

	methods: {
		...mapActions(useCirclesStore, [
			'getCircles',
			'addMembersToCircle',
		]),

		...mapActions(useCollectivesStore, ['newCollective']),

		setNameIsTooShort() {
			this.nameIsTooShort = true
		},

		setNameIsTooLong() {
			this.nameIsTooLong = true
		},

		clearName() {
			this.name = ''
		},

		advanceToMembers() {
			if (this.newCollectiveName && !this.nameIsInvalid) {
				this.state = 1
			}
		},

		onClose() {
			this.$emit('close')
		},

		// Create a new collective and navigate to it
		onCreate() {
			const updateCollective = () => {
				if (this.updatedCollective && this.hasSelectedMembersWithoutSelf) {
					const members = Object.values(this.selectedMembersWithoutSelf).map((entry) => ({
						id: entry.id,
						type: circlesMemberTypes[autocompleteSourcesToCircleMemberTypes[entry.source]],
					}))
					try {
						this.addMembersToCircle({ circleId: this.updatedCollective.circleId, members })
					} catch {
						showError(t('collectives', 'Could not add members to the collective'))
					}
				}

				if (this.collectiveChanged) {
					this.$router.push(this.updatedCollectivePath)
				}

				this.onClose()
			}

			this.loading = true
			this.newCollective({ name: this.newCollectiveName, emoji: this.emoji })
				.then((message) => {
					if (message) {
						showInfo(message)
					}
					updateCollective()
				})
				.catch((e) => {
					if (e.response?.status === 400) {
						this.nameExists = this.newCollectiveName
						this.state = 0
					} else {
						displayError('Could not create the collective')(e)
					}
				})
				.finally(() => {
					this.loading = false
				})
		},

		startSelectCircle() {
			this.pickCircle = true
			this.$nextTick(() => {
				this.$refs.circleSelector.$el.getElementsByTagName('input')[0]?.focus()
			})
		},

		stopSelectCircle() {
			this.pickCircle = false
			this.circle = null
			this.$nextTick(() => {
				this.$refs.collectiveName.$el.getElementsByTagName('input')[0]?.focus()
			})
		},

		updateEmoji(emoji) {
			this.emoji = emoji
		},

		addMember(member) {
			this.$set(this.selectedMembers, `${member.source}-${member.id}`, member)
		},

		deleteMember(member) {
			if (member.source === 'users' && member.id === this.currentUserId) {
				return
			}
			this.$delete(this.selectedMembers, `${member.source}-${member.id}`, member)
		},

		onClickSearched(member) {
			if (`${member.source}-${member.id}` in this.selectedMembers) {
				this.deleteMember(member)
				return
			}
			this.addMember(member)
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(div.modal-wrapper > div.modal-container) {
	height: min(90%, 100% - 100px);
	max-height: 650px;
}

:deep(div.dialog__actions) {
	justify-content: space-between;
}

.modal-collective-wrapper {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.modal-collective-name {
	display: flex;
	flex-direction: row;
	gap: 4px;
	align-items: center;
	height: calc(var(--default-clickable-area) + 12px);

	.button-emoji {
		padding: 0;
		font-size: 1.2em;
	}

	.collective-name {
		padding-block-end: 6px;
	}

	.circle-selector {
		width: 100%;
	}
}

.modal-collective-name-error-placeholder {
	min-height: 24px;
}

.modal-collective-name-error {
	display: flex;
	// Emoji button + gap + input field padding
	padding-left: calc(var(--default-clickable-area) + 4px + 9px);
	color: var(--color-text-maxcontrast);

	&-label {
		padding-left: 4px;
	}
}

.modal-collective-members {
	// Required for sticky search field and buttons
	height: 100%;
}
</style>
