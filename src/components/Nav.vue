<template>
	<AppNavigation>
		<template #list>
			<AppNavigationCaption :title="t('collectives', 'Select a collective')" />
			<AppNavigationItem v-for="collective in collectives"
				:key="collective.circleUniqueId"
				:title="collective.title"
				:class="{active: isActive(collective)}"
				:to="`/${encodeURIComponent(collective.name)}`"
				:icon="icon(collective)"
				:force-menu="true">
				<template v-if="collective.emoji" #icon>
					{{ collective.emoji }}
				</template>
				<template #actions>
					<ActionButton icon="icon-delete" @click="deleteCollective(collective)">
						{{ t('collectives', 'Delete') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
			<NewCollective @newCollective="newCollective" />
		</template>
	</AppNavigation>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import NewCollective from './NewCollective'

export default {
	name: 'Nav',
	components: {
		ActionButton,
		AppNavigation,
		AppNavigationItem,
		AppNavigationCaption,
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
		deleteCollective(collective) {
			this.$emit('deleteCollective', collective)
		},
	},
}
</script>
