<template>
	<AppNavigationSettings :title="t('collectives', 'Deleted collectives')">
		<ul class="app-navigation__list">
			<AppNavigationItem v-for="collective in trashCollectives"
				:key="collective.circleUniqueId"
				:title="collective.title"
				:icon="icon(collective)"
				:force-menu="true"
				class="collectives_trash_list_item">
				<template v-if="collective.emoji" #icon>
					{{ collective.emoji }}
				</template>
				<template #actions>
					<ActionButton icon="icon-history" @click="restoreCollective(collective)">
						{{ t('collectives', 'Restore collective') }}
					</ActionButton>
					<ActionButton icon="icon-delete" @click="deleteCollective(collective, false)">
						{{ t('collectives', 'Permanently delete collective') }}
					</ActionButton>
					<ActionButton icon="icon-delete" @click="deleteCollective(collective, true)">
						{{ t('collectives', 'Permanently delete collective and circle') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
		</ul>
	</AppNavigationSettings>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'

export default {
	name: 'CollectiveTrash',
	components: {
		ActionButton,
		AppNavigationItem,
		AppNavigationSettings,
	},
	computed: {
		trashCollectives() {
			return this.$store.getters.trashCollectives
		},
	},
	methods: {
		icon(collective) {
			return collective.emoji ? '' : 'icon-star'
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

<style lang="scss">
#app-settings-header .settings-button {
	background-image: var(--icon-delete-000);
}
</style>
