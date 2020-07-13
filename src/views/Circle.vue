<template>
	<div id="content" class="app-wiki">
		{{ circle && circle.name }}
	</div>
</template>

<script>
export default {
	name: 'Circle',

	props: {
		id: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			loading: false,
			circle: null,
		}
	},

	mounted() {
		this.getCircle()
	},

	methods: {
		/**
		 * Get Circle data
		 */
		getCircle() {
			this.loading = true
			const view = this
			OCA.Circles.api.detailsCircle(this.id, function(response) {
				view.circle = response.details
				view.loading = false
			})
		},
	},

}
</script>
