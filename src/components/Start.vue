<template>
	<AppContent>
		<div id="emptycontent">
			<div class="icon-file" />
			<h2>{{ t('wiki', 'Create a page to get started') }}</h2>
		</div>
	</AppContent>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
export default {
	name: 'Start',

	components: {
		AppContent,
	},
}
</script>
