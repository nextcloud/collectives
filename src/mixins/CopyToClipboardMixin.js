import { showError, showSuccess } from '@nextcloud/dialogs'
import Vue from 'vue'
import VueClipboard from 'vue-clipboard2'

Vue.use(VueClipboard)

export default {
	data() {
		return {
			copied: false,
			copyLoading: false,
			copySuccess: false,
		}
	},

	computed: {
		copyLinkIcon() {
			if (this.copySuccess) {
				return 'icon-checkmark'
			}
			if (this.copyLoading) {
				return 'icon-loading-small'
			}
			return 'icon-clippy'
		},
	},

	methods: {
		async copyToClipboard(url) {
			// change to loading status
			this.copyLoading = true

			// copy link to clipboard
			try {
				// Unfortunately, $copyText closes the action menu.
				// See https://github.com/Inndy/vue-clipboard2/issues/46
				await this.$copyText(url)
				this.copySuccess = true
				this.copied = true

				// Notify success
				showSuccess(t('collectives', 'Link copied to the clipboard.'))
			} catch (error) {
				this.copySuccess = false
				this.copied = true
				showError(
					`<div>${t('collectives', 'Could not copy link to the clipboard:')}</div><div>${url}</div>`,
					{ isHTML: true })
			} finally {
				this.copyLoading = false
				setTimeout(() => {
					// stop loading status regardless of outcome
					this.copied = false
					this.copySuccess = false
				}, 2000)
			}
		},
	},
}
