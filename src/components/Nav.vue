<template>
	<AppNavigation>
		<template #list>
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

		<template #footer>
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
import NewCollective from './NewCollective'

export default {
	name: 'Nav',
	components: {
		ActionButton,
		AppNavigation,
		AppNavigationItem,
		AppNavigationCaption,
		CollectiveTrash,
		NewCollective,
	},
	computed: {
		collectives() {
			return this.$store.getters.collectives
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
