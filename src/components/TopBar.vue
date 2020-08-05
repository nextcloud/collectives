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
-->

<template>
	<div class="top-bar">
		<!-- edit toggle -->
		<Actions class="top-bar__button" close-after-click="true">
			<ActionButton v-if="edit"
				icon="icon-edit"
				@click="$emit('toggleEdit')">
				{{ t('wiki', 'View page without editing') }}
			</ActionButton>
			<ActionButton v-else
				icon="icon-rename"
				@click="$emit('toggleEdit')">
				{{ t('wiki', 'Edit page') }}
			</ActionButton>
		</Actions>
		<!-- sidebar toggle -->
		<Actions class="top-bar__button" close-after-click="true">
			<ActionButton v-if="!sidebar"
				icon="icon-menu"
				@click="$emit('toggleSidebar')">
				{{ t('wiki', 'Show sidebar') }}
			</ActionButton>
		</Actions>
	</div>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'TopBar',

	components: {
		ActionButton,
		Actions,
	},

	props: {
		edit: {
			type: Boolean,
			required: true,
		},
		sidebar: {
			type: Boolean,
			required: true,
		},
	},

	methods: {
		openSidebar() {
			this.$emit('showSidebar')
		},
	},

}

</script>
<style lang="scss" scoped>

.top-bar {
	height: 40px;
	position: absolute;
	top: 0;
	right: 0;
	display: flex;
	z-index: 10;
	justify-content: flex-end;
	padding: 0 6px;
	&__button {
		margin: 0 2px;
		align-self: center;
		display: flex;
		align-items: center;
		svg {
			margin-right: 4px !important;
		}
		.icon {
			margin-right: 4px !important;
		}
	}

}

.hint {
	padding: 4px;
	text-align: left;
	&__actions{
		display: flex;
		justify-content: space-between;
		padding-top:4px;
	}
}
</style>
