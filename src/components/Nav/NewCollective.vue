<!--
  - @copyright Copyright (c) 2020 Azul <azul@riseup.net>
  -
  - @author Azul <azul@riseup.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<NcAppNavigationItem v-if="!editing"
		:title="t('collectives', 'Create new collective')"
		icon="icon-add"
		@click.prevent.stop="startCreateCollective">
		<template #actions>
			<NcActionButton v-if="anyCircle" @click.stop.prevent="startSelectCircle">
				<template #icon>
					<CirclesIcon :size="16" />
				</template>
				{{ t('collectives', 'Create collective for existing circle') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
	<form v-else
		v-show="editing"
		class="collective-create"
		@submit.prevent.stop="createCollective">
		<NcEmojiPicker :show-preview="true" @select="addEmoji">
			<NcButton type="tertiary"
				:aria-label="t('collectives', 'Select emoji for collective')"
				:title="t('collectives', 'Select emoji')"
				class="button-emoji"
				@click.prevent>
				{{ emoji }}
			</NcButton>
		</NcEmojiPicker>

		<NcTextField v-if="!pickCircle"
			ref="nameField"
			:value.sync="text"
			:error="text === ''"
			:label="t('collectives', 'New collective name')"
			:show-trailing-button="!!text"
			required
			trailing-button-icon="arrowRight"
			:trailing-button-label="t('collectives', 'Create new collective')"
			@trailing-button-click="createCollective" />

		<div v-else class="circle-selector--container">
			<NcSelect ref="circleSelector"
				v-model="circle"
				:loading="loading"
				:options="circles"
				:searchable="!circle"
				open-direction="below"
				:placeholder="t('collectives', 'Select circle …')"
				required />
			<NcButton :disabled="loading || !circle"
				:aria-label="t('collectives', 'Create a new collective')"
				type="tertiary-no-background"
				@click="createCollective">
				<template #icon>
					<ArrowRightIcon :size="20" />
				</template>
			</NcButton>
		</div>

		<NcActions>
			<NcActionButton icon="icon-close"
				:aria-label="t('collectives', 'Cancel creating a new collective')"
				@click.stop.prevent="cancelEdit" />
		</NcActions>
	</form>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { NcActionButton, NcActions, NcAppNavigationItem, NcButton, NcSelect, NcTextField } from '@nextcloud/vue'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import { mapActions, mapGetters } from 'vuex'
import CirclesIcon from '../Icon/CirclesIcon.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import { GET_CIRCLES, NEW_COLLECTIVE } from '../../store/actions.js'
import displayError from '../../util/displayError.js'

const randomColor = () => '#' + ((1 << 24) * Math.random() | 0).toString(16)

export default {
	name: 'NewCollective',
	components: {
		ArrowRightIcon,
		CirclesIcon,
		NcAppNavigationItem,
		NcActionButton,
		NcActions,
		NcButton,
		NcEmojiPicker,
		NcSelect,
		NcTextField,
	},
	directives: {},
	data() {
		return {
			classes: [],
			editing: false,
			loading: false,
			color: randomColor(),
			emoji: null,
			text: null,
			circle: null,
			pickCircle: false,
		}
	},
	computed: {
		...mapGetters([
			'randomCollectiveEmoji',
		]),
		name() {
			if (this.pickCircle) {
				return this.circle
			} else {
				return this.text
			}
		},
		circles() {
			return this.$store.getters.availableCircles.map(c => c.sanitizedName)
		},
		anyCircle() {
			return this.circles.length > 0
		},
	},

	mounted() {
		this.getCircles()
		subscribe('start-new-collective', this.startCreateCollective)
	},

	unmounted() {
		unsubscribe('start-new-collective', this.startCreateCollective)
	},

	methods: {
		...mapActions({
			dispatchGetCircles: GET_CIRCLES,
			dispatchNewCollective: NEW_COLLECTIVE,
		}),

		/**
		 * Get list of all circles
		 *
		 * @return {Promise}
		 */
		getCircles() {
			return this.dispatchGetCircles()
				.catch(displayError('Could not fetch circles'))
		},

		startCreateCollective(e) {
			this.editing = true
			this.emoji = this.randomCollectiveEmoji()
			this.$nextTick(() => {
				this.$refs.nameField.$el.focus()
			})
		},

		startSelectCircle(e) {
			this.editing = true
			this.emoji = this.randomCollectiveEmoji()
			this.pickCircle = true
			this.$nextTick(() => {
				this.$refs.circleSelector.$el.focus()
			})
		},

		/**
		 * Create a new collective with the name given in the input
		 *
		 * @param {Event} e - trigger event
		 */
		createCollective(e) {
			if (!this.name) return

			const updateCollective = () => {
				this.clear()
				if (this.$store.getters.collectiveChanged) {
					this.$router.push(this.$store.getters.updatedCollectivePath)
				}
			}
			const done = () => {
				this.loading = false
			}
			this.loading = true
			this.dispatchNewCollective({ name: this.name, emoji: this.emoji })
				.then(updateCollective)
				.catch(displayError('Could not create the collective'))
				.finally(done)
		},
		cancelEdit(e) {
			this.clear()
		},
		addEmoji(emoji) {
			this.emoji = emoji
		},
		clear() {
			this.editing = false
			this.pickCircle = false
			this.emoji = null
			this.text = null
		},
	},
}
</script>

<style lang="scss" scoped>
.collective-create {
	display: flex;
	align-items: center;

	.circle-selector--container {
		display: flex;
		align-items: center;
		flex-grow: 1;
		min-width: 0;

		.v-select.select {
			min-width: 100%;
			flex-grow: 1;
		}
		:deep(.vs__actions) {
			margin-right: 32px;
		}
		.button-vue {
			position: relative;
			left: -44px;
		}
	}
}

.popover button {
	background-color: var(--color-main-background);
	border: none;
	font-size: 14px;
}

.button-emoji {
	font-size: 20px;
	padding: 0;
}
</style>
