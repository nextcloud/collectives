<template>
	<AppNavigation>
		<template #list>
			<AppNavigationCaption :title="t('collectives', 'Select a collective')" />
			<AppNavigationItem v-for="collective in collectives"
				:key="collective.circleUniqueId"
				:title="collective.title"
				:class="{active: isActive(collective)}"
				:to="`/${encodeURIComponent(collective.name)}`"
				:icon="icon(collective)">
				<template v-if="collective.emoji" #icon>
					{{ collective.emoji }}
				</template>
			</AppNavigationItem>
			<NewCollective @newCollective="newCollective" />
		</template>
	</AppNavigation>
</template>

<script>
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import NewCollective from './NewCollective'

export default {
	name: 'Nav',
	components: {
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
	},
}
</script>
