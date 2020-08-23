<template>
	<AppNavigation>
		<template #list>
			<AppNavigationCaption :title="t('collectives', 'Select a collective')" />
			<AppNavigationItem v-for="collective in collectives"
				:key="collective.circleUniqueId"
				:title="text(collective)"
				:class="{active: isActive(collective)}"
				:to="`/${collective.name}`"
				:icon="icon(collective)">
				<template v-if="emoji(collective)" v-slot:icon>
					{{ emoji(collective) }}
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
			return this.$store.state.collectives
		},
	},
	methods: {
		isActive(collective) {
			return this.$store.getters.collectiveParam === collective.name
		},
		newCollective(collective) {
			this.$emit('newCollective', collective)
		},
		text(collective) {
			const name = collective.name
			if (this.emoji(collective)) {
				return name.substring(0, name.length - 2).trim()
			}
			return name
		},
		// returns the last grapheme in the collective name if it's a 2 byte utf8 char
		emoji(collective) {
			const arr = [...collective.name]
			const last = arr[arr.length - 1]
			if (last && last.length === 2) {
				return last
			}
			return null
		},
		icon(collective) {
			return !this.emoji(collective) && 'icon-star'
		},
	},
}
</script>
