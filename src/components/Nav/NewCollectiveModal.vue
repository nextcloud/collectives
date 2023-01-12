<template>
	<NcModal :title="t('collectives', 'New collective')" @close="onClose">
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
						@trailing-button-click="clearName" />
					<NcSelect v-else
						ref="circleSelector"
						v-model="circle"
						class="circle-selector"
						:options="circles"
						:placeholder="t('collectives', 'Select a circle...')" />
					<NcButton v-if="anyCircle && !pickCircle"
						:title="t('collectives', 'Select an existing circle')"
						type="tertiary"
						@click.stop.prevent="startSelectCircle">
						<template #icon>
							<CirclesIcon :size="16" />
						</template>
					</NcButton>
					<NcButton v-if="anyCircle && pickCircle"
						:title="t('collectives', 'Cancel selecting a circle')"
						type="tertiary"
						@click.stop.prevent="stopSelectCircle">
						<template #icon>
							<CloseIcon :size="16" />
						</template>
					</NcButton>
				</div>
				<div v-if="nameError" class="modal-collective-name-error">
					<AlertCircleOutlineIcon :size="16" />
					<label for="collective-name" class="modal-collective-name-error-label">
						{{ nameError }}
					</label>
				</div>

				<div class="modal-buttons">
					<NcButton @click="onClose">
						{{ t('collectives', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="!newCollectiveName || nameIsInvalid"
						class="modal-buttons-right"
						@click="state = 1">
						{{ t('collectives', 'Select members') }}
					</NcButton>
				</div>
			</div>

			<div v-else-if="state === 1" class="modal-collective-wrapper">
				<h2 class="modal-collective-title">
					{{ t('collectives', 'Members') }}
				</h2>

				<div class="modal-collective-members">
					<MemberPicker :selection-set="selectedMembers"
						@updateSelection="updateSelectedMembers" />
				</div>

				<div class="modal-buttons">
					<NcButton @click="state = 0">
						{{ t('collectives', 'Back') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="loading"
						class="modal-buttons-right"
						@click="onCreate">
						{{ t('collectives', 'Create') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex'
import { ADD_MEMBERS_TO_CIRCLE, GET_CIRCLES, NEW_COLLECTIVE } from '../../store/actions.js'
import displayError from '../../util/displayError.js'
import { NcButton, NcEmojiPicker, NcModal, NcSelect, NcTextField } from '@nextcloud/vue'
import AlertCircleOutlineIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CirclesIcon from '../Icon/CirclesIcon.vue'
import MemberPicker from '../Member/MemberPicker.vue'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'NewCollectiveModal',

	components: {
		AlertCircleOutlineIcon,
		CloseIcon,
		CirclesIcon,
		MemberPicker,
		NcButton,
		NcEmojiPicker,
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
			selectedMembers: {},
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
	},

	mounted() {
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

		async getCircles() {
			return await this.dispatchGetCircles()
				.catch(displayError('Could not fetch circles'))
		},

		onClose() {
			this.$emit('close')
		},

		// Create a new collective and navigate to it
		onCreate() {
			const updateCollective = () => {
				if (this.updatedCollective && this.selectedMembers) {
					const selectedMembers = Object.values(this.selectedMembers).map(entry => ({
						id: entry.shareWith,
						type: entry.type,
					}))
					try {
						this.dispatchAddMembersToCircle({ collective: this.updatedCollective, members: selectedMembers })
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
					if (e.response?.data === 'A circle with that name exists') {
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

		updateSelectedMembers(selectedMembers) {
			this.selectedMembers = selectedMembers
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
	padding-bottom: 0;
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
		font-size: 20px;
	}

	.circle-selector {
		width: 100%;
	}
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
	height: 100%;
}

.modal-buttons {
	z-index: 1;
	display: flex;
	flex: 0 0;
	justify-content: space-between;
	width: 100%;
	background-color: var(--color-main-background);
	box-shadow: 0 -10px 5px var(--color-main-background);
	padding: 16px 0;
	// Sticky to the bottom
	position: sticky;
	bottom: 0;
	margin-top: auto;

	&-right {
		margin-left: auto;
	}
}
</style>
