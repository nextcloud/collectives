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
	<AppNavigationItem v-if="!editing"
		:title="t('collectives', 'Create new collective')"
		icon="icon-add"
		@click.prevent.stop="startCreateCollective" />
	<div v-else class="collective-create">
		<form @submit.prevent.stop="createCollective">
			<EmojiPicker :show-preview="true" @select="addEmoji">
				<button
					type="button"
					:aria-label="t('collectives', 'Add emoji')"
					:aria-haspopup="true">
					<span v-if="emoji">{{ emoji }}</span>
					<EmoticonOutline v-else :size="20" />
				</button>
			</EmojiPicker>
			<input
				ref="nameField"
				v-model="text"
				:placeholder="t('collectives', 'New collective name')"
				type="text"
				required>
			<input type="submit" value="" class="icon-confirm">
			<Actions>
				<ActionButton icon="icon-close" @click.stop.prevent="cancelEdit" />
			</Actions>
		</form>
	</div>
</template>

<script>
import { ActionButton, Actions, AppNavigationItem } from '@nextcloud/vue'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline'

const randomColor = () => '#' + ((1 << 24) * Math.random() | 0).toString(16)

export default {
	name: 'NewCollective',
	components: {
		AppNavigationItem,
		ActionButton,
		Actions,
		EmojiPicker,
		EmoticonOutline,
	},
	directives: {},
	props: {},
	data() {
		return {
			classes: [],
			editing: false,
			loading: false,
			color: randomColor(),
			emoji: null,
			text: null,
		}
	},
	computed: {
		name() {
			return this.emoji ? `${this.text} ${this.emoji}` : this.text
		},
	},
	watch: {},
	mounted() {},
	methods: {
		startCreateCollective(e) {
			this.editing = true
			this.$nextTick(() => {
				this.$refs.nameField.focus()
			})
		},
		createCollective(e) {
			const collective = {
				name: this.name,
			}
			this.$emit('newCollective', collective)
			this.editing = false
		},
		cancelEdit(e) {
			this.editing = false
			this.emoji = null
		},
		addEmoji(emoji) {
			this.emoji = emoji
		},
	},
}
</script>
<style lang="scss" scoped>
.collective-create {
	order: 1;
	display: flex;
	height: 44px;

	form {
		display: flex;
		flex-grow: 1;

		input[type='text'] {
			flex-grow: 1;
		}
	}
}
.popover button {
    background-color: var(--color-main-background);
    border: none;
    font-size: 14px;
}
</style>
