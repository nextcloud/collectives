<template>
	<NcModal :title="t('collectives', 'Create new collective')" @close="onClose">
		<div class="modal-content">
			<h2 class="modal-title">
				{{ t('collectives', 'Create new collective') }}
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
			<div class="modal-buttons">
				<NcButton @click="onClose">
					{{ t('collectives', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" :disabled="loading" @click="onCreate">
					{{ t('collectives', 'Create') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import { GET_CIRCLES, NEW_COLLECTIVE } from '../../store/actions.js'
import displayError from '../../util/displayError.js'
import { NcButton, NcEmojiPicker, NcModal, NcSelect, NcTextField } from '@nextcloud/vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CirclesIcon from '../Icon/CirclesIcon.vue'

export default {
	name: 'NewCollectiveModal',

	components: {
		CloseIcon,
		CirclesIcon,
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
			pickCircle: false,
		}
	},

	computed: {
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
	},

	mounted() {
		this.emoji = this.randomCollectiveEmoji()
		this.getCircles()
	},

	methods: {
		...mapActions({
			dispatchGetCircles: GET_CIRCLES,
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
				if (this.collectiveChanged) {
					this.$router.push(this.updatedCollectivePath)
				}
			}
			const done = () => {
				this.loading = false
				this.onClose()
			}
			this.loading = true
			this.dispatchNewCollective({ name: this.newCollectiveName, emoji: this.emoji })
				.then(updateCollective)
				.catch(displayError('Could not create the collective'))
				.finally(done)
		},

		startSelectCircle() {
			this.pickCircle = true
			this.$nextTick(() => {
				this.$refs.circleSelector.$el.getElementsByTagName('input')[0].focus()
			})
		},

		stopSelectCircle() {
			this.pickCircle = false
			this.circle = null
			this.$nextTick(() => {
				this.$refs.collectiveName.$el.getElementsByTagName('input')[0].focus()
			})
		},

		updateEmoji(emoji) {
			this.emoji = emoji
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

	h2 {
		text-align: center;
	}
}

.modal-collective-name {
	display: flex;
	flex-direction: row;
	align-items: center;

	.button-emoji {
		font-size: 20px;
	}

	.collective-name {
		min-height: 48px;

		:deep(input) {
			height: 48px !important;
		}
	}

	.circle-selector {
		width: 100%;
	}
}

.modal-buttons {
	display: flex;
	justify-content: space-between;
	padding: 16px 8px;
}
</style>
