<template>
	<AppNavigation>
		<template v-if="loading" #default>
			<EmptyContent icon="icon-loading" />
		</template>
		<template v-else #list>
			<AppNavigationCaption :title="t('collectives', 'Select a collective')" />
			<AppNavigationItem v-for="collective in collectives"
				:key="collective.circleUniqueId"
				:title="collective.name"
				:class="{active: isActive(collective)}"
				:to="`/${encodeURIComponent(collective.name)}`"
				:icon="icon(collective)"
				:force-menu="true"
				class="collectives_list_item">
				<template v-if="collective.emoji" #icon>
					{{ collective.emoji }}
				</template>
				<template v-if="collective.admin" #actions>
					<ActionButton icon="icon-delete" @click="trashCollective(collective)">
						{{ t('collectives', 'Delete') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
			<NewCollective />
		</template>
		<template v-if="!loading" #footer>
			<CollectiveTrash
				@restoreCollective="restoreCollective"
				@deleteCollective="deleteCollective" />
		</template>
	</AppNavigation>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import CollectiveTrash from '../components/CollectiveTrash'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import NewCollective from './NewCollective'

export default {
	name: 'Nav',
	components: {
		ActionButton,
		AppNavigation,
		AppNavigationItem,
		AppNavigationCaption,
		CollectiveTrash,
		EmptyContent,
		NewCollective,
	},
	computed: {
		collectives() {
			return this.$store.getters.collectives
		},
		loading() {
			return this.$store.getters.loading('collectives')
		},
	},
	methods: {
		isActive(collective) {
			return this.$store.getters.collectiveParam === collective.name
		},
		newCollective(collective) {
			this.$emit('newCollective', collective)
		},
		icon(collective) {
			return collective.emoji ? '' : 'icon-star'
		},
		trashCollective(collective) {
			this.$emit('trashCollective', collective)
		},
		restoreCollective(collective) {
			this.$emit('restoreCollective', collective)
		},
		deleteCollective(collective, circle) {
			this.$emit('deleteCollective', collective, circle)
		},
	},
}
</script>
