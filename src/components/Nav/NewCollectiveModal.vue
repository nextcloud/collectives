<template>
	<NcModal :name="t('collectives', 'New collective')" @close="onClose">
		<div class="modal-content">
			<div v-if="state === 0" class="modal-collective-wrapper">
				<h2 class="modal-collective-title">
					{{ t('collectives', 'New collective') }}
				</h2>

				<div class="modal-collective-name">
					<NcEmojiPicker :show-preview="true" @select="updateEmoji">
						<NcButton type="tertiary"
							:aria-label="t('collectives', 'Select emoji for collective')"
							:title="t('collectives', 'Select emoji')"
							class="button-emoji"
							@click.prevent>
							{{ emoji }}
						</NcButton>
					</NcEmojiPicker>
					<NcTextField v-if="!pickCircle"
						ref="collectiveName"
						:value.sync="name"
						class="collective-name"
						:error="nameIsInvalid"
						:show-trailing-button="name !== ''"
						:label="t('collectives', 'Name of the collective')"
						@keypress.enter.prevent="advanceToMembers"
						@trailing-button-click="clearName" />
					<NcSelect v-else
						ref="circleSelector"
						v-model="circle"
						class="circle-selector"
						:append-to-body="false"
						:options="circles"
						:aria-label-combobox="t('collectives', 'Select an existing team')"
						:placeholder="t('collectives', 'Select a team...')" />
					<NcButton v-if="anyCircle && !pickCircle"
						:title="t('collectives', 'Select an existing team')"
						type="tertiary"
						@click.stop.prevent="startSelectCircle">
						<template #icon>
							<TeamsIcon :size="16" />
						</template>
					</NcButton>
					<NcButton v-if="anyCircle && pickCircle"
						:title="t('collectives', 'Cancel selecting a team')"
						type="tertiary"
						@click.stop.prevent="stopSelectCircle">
						<template #icon>
							<CloseIcon :size="16" />
						</template>
					</NcButton>
				</div>
				<div class="modal-collective-name-error-placeholder">
					<div v-if="nameError" class="modal-collective-name-error">
						<AlertCircleOutlineIcon :size="16" />
						<label for="collective-name" class="modal-collective-name-error-label">
							{{ nameError }}
						</label>
					</div>
				</div>

				<NcEmptyContent :title="t('collectives', 'Enter the new collective name or pick an existing team')"
					class="empty-content">
					<template #icon>
						<CollectivesIcon :size="20" />
					</template>
				</NcEmptyContent>

				<div class="modal-buttons">
					<NcButton @click="onClose">
						{{ t('collectives', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="!newCollectiveName || nameIsInvalid"
						class="modal-buttons-right"
						@click="advanceToMembers">
						{{ t('collectives', 'Add members') }}
					</NcButton>
				</div>
			</div>

			<div v-else-if="state === 1" class="modal-collective-wrapper">
				<h2 class="modal-collective-title">
					{{ t('collectives', 'Add members to {name}', { name: newCollectiveName }) }}
				</h2>

				<div class="modal-collective-members">
					<MemberPicker :show-selection="true"
						:selected-members="selectedMembers"
						:no-delete-members="noDeleteMembers"
						:on-click-searched="onClickSearched"
						@delete-from-selection="deleteMember" />
				</div>

				<div class="modal-buttons">
					<NcButton @click="state = 0">
						{{ t('collectives', 'Back') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="loading"
						class="modal-buttons-right"
						@click="onCreate">
						{{ createButtonString }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { NcButton, NcEmojiPicker, NcEmptyContent, NcModal, NcSelect, NcTextField } from '@nextcloud/vue'
import { ADD_MEMBERS_TO_CIRCLE, GET_CIRCLES, NEW_COLLECTIVE } from '../../store/actions.js'
import displayError from '../../util/displayError.js'
import { autocompleteSourcesToCircleMemberTypes, circlesMemberTypes } from '../../constants.js'
import AlertCircleOutlineIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import TeamsIcon from '../Icon/TeamsIcon.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import MemberPicker from '../Member/MemberPicker.vue'

export default {
	name: 'NewCollectiveModal',

	components: {
		AlertCircleOutlineIcon,
		TeamsIcon,
		CloseIcon,
		CollectivesIcon,
		MemberPicker,
		NcButton,
		NcEmojiPicker,
		NcEmptyContent,
		NcModal,
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
			state: 0,
		}
	},

	computed: {
		...mapState({
			updatedCollective: (state) => state.collectives.updatedCollective,
		}),

		...mapGetters([
			'availableCircles',
			'collectiveChanged',
			'randomCollectiveEmoji',
			'updatedCollectivePath',
		]),

		circles() {
			return this.availableCircles.map(c => c.sanitizedName)
		},

		anyCircle() {
			return this.circles.length > 0
		},

		newCollectiveName() {
			return this.pickCircle ? this.circle : this.name
		},

		nameIsTooShort() {
			return !!this.newCollectiveName && this.newCollectiveName.length < 3
		},

		nameIsTaken() {
			return !!this.newCollectiveName && this.nameExists === this.newCollectiveName
		},

		nameIsInvalid() {
			return this.nameIsTooShort || this.nameIsTaken
		},

		nameError() {
			if (this.nameIsTooShort) {
				return t('collectives', 'Name too short, requires at least three characters')
			} else if (this.nameIsTaken) {
				return t('collectives', 'A collective with this name already exists')
			}
			return null
		},

		selectedMembersWithoutSelf() {
			return Object.keys(this.selectedMembers)
				.filter(key => key !== `users-${this.currentUserId}`)
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

	mounted() {
		this.selectedMembers[`users-${this.currentUserId}`] = {
			icon: 'icon-user',
			id: this.currentUserId,
			label: this.currentUserId,
			source: 'users',
		}
		this.noDeleteMembers = [`users-${this.currentUserId}`]
		this.emoji = this.randomCollectiveEmoji()
		this.getCircles()
		this.$nextTick(() => {
			this.$refs.collectiveName.$el.getElementsByTagName('input')[0]?.focus()
		})
	},

	methods: {
		...mapActions({
			dispatchGetCircles: GET_CIRCLES,
			dispatchAddMembersToCircle: ADD_MEMBERS_TO_CIRCLE,
			dispatchNewCollective: NEW_COLLECTIVE,
		}),

		clearName() {
			this.name = ''
		},

		advanceToMembers() {
			if (this.newCollectiveName && !this.nameIsInvalid) {
				this.state = 1
			}
		},

		async getCircles() {
			return await this.dispatchGetCircles()
				.catch(displayError('Could not get list of teams'))
		},

		onClose() {
			this.$emit('close')
		},

		// Create a new collective and navigate to it
		onCreate() {
			const updateCollective = () => {
				if (this.updatedCollective && this.hasSelectedMembersWithoutSelf) {
					const members = Object.values(this.selectedMembersWithoutSelf).map(entry => ({
						id: entry.id,
						type: circlesMemberTypes[autocompleteSourcesToCircleMemberTypes[entry.source]],
					}))
					try {
						this.dispatchAddMembersToCircle({ circleId: this.updatedCollective.circleId, members })
					} catch (e) {
						showError(t('collectives', 'Could not add members to the collective'))
					}
				}

				if (this.collectiveChanged) {
					this.$router.push(this.updatedCollectivePath)
				}

				this.onClose()
			}

			this.loading = true
			this.dispatchNewCollective({ name: this.newCollectiveName, emoji: this.emoji })
				.then(updateCollective)
				.catch((e) => {
					if (e.response?.data === 'A team with that name exists') {
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
.modal-content {
	display: flex;
	flex-direction: column;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 16px;
	padding-bottom: 18px;
}

.modal-collective-wrapper {
	display: flex;
	flex-direction: column;
	width: 100%;
	height: 550px;
}

.modal-collective-name {
	display: flex;
	flex-direction: row;
	align-items: center;

	.button-emoji {
		padding: 0;
		width: 44px;
		font-size: 20px;
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
	// Emoji button + input field padding
	padding-left: calc(57px + 12px);

	&-label {
		padding-left: 4px;
	}
}

.modal-collective-members {
	// Full height minus search field and buttons
	// Required for sticky search field and buttons
	height: calc(100% - 30px - 76px);
}

.modal-buttons {
	z-index: 1;
	display: flex;
	flex: 0 0;
	justify-content: space-between;
	width: 100%;
	background-color: var(--color-main-background);
	box-shadow: 0 -10px 5px var(--color-main-background);
	// Sticky to the bottom
	position: sticky;
	bottom: 0;
	margin-top: auto;

	&-right {
		margin-left: auto;
	}
}
</style>
