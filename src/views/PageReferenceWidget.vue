<!--
  - @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author 2022 Julien Veyssier <eneiluj@posteo.net>
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
	<div v-if="richObject" class="collective-page">
		<div class="collective-page--image">
			<span v-if="emoji"
				class="page-emoji">
				{{ emoji }}
			</span>
			<CollectivesIcon v-else
				:size="50" />
		</div>
		<div class="collective-page--info">
			<div class="line">
				<strong>
					<a :href="richObject.link" target="_blank">
						{{ richObject.page.title }}
					</a>
				</strong>
			</div>
			<div class="description">
				{{ richObject.description }}
			</div>
			<div class="last-edited">
				{{ richObject.lastEdited }}
				<NcUserBubble :user="richObject.page.lastUserId"
					:display-name="richObject.page.lastUserDisplayName" />
			</div>
		</div>
	</div>
</template>

<script>
import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'
import NcUserBubble from '@nextcloud/vue/dist/Components/NcUserBubble.js'

export default {
	name: 'PageReferenceWidget',

	components: {
		CollectivesIcon,
		NcUserBubble,
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		emoji() {
			return this.richObject.page.emoji ?? this.richObject.collective.emoji ?? null
		},
	},
}
</script>

<style scoped lang="scss">
.collective-page {
	width: 100%;
	white-space: normal;
	padding: 12px;
	display: flex;

	a {
		padding: 0 !important;
		&:not(:hover) {
			text-decoration: unset !important;
		}
	}

	&--image {
		margin-right: 12px;
		display: flex;
		align-items: center;
		.page-emoji {
			display: flex;
			align-items: center;
			height: 50px;
			font-size: 50px;
		}
	}

	.spacer {
		flex-grow: 1;
	}
}
</style>
