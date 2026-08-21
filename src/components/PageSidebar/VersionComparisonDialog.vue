<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:open="open"
		isForm
		:size="comparisonDialogSize()"
		contentClasses="version-comparison-dialog"
		:name="t('collectives', 'Compare versions')"
		@submit="compareSelected"
		@update:open="onOpenUpdate">
		<div class="version-comparison-dialog__selectors">
			<label class="version-comparison-dialog__field">
				<span class="version-comparison-dialog__field-label">
					<span class="version-comparison-dialog__indicator" aria-hidden="true" />
					{{ t('collectives', 'Earlier') }}
				</span>
				<select
					v-model="earlierKey"
					:disabled="status === 'loading'"
					@change="resetResult">
					<option
						v-for="option in comparisonOptions"
						:key="option.key"
						:value="option.key">
						{{ optionLabel(option) }}
					</option>
				</select>
			</label>
			<label class="version-comparison-dialog__field version-comparison-dialog__field--later">
				<span class="version-comparison-dialog__field-label">
					<span class="version-comparison-dialog__indicator" aria-hidden="true" />
					{{ t('collectives', 'Later') }}
				</span>
				<select
					v-model="laterKey"
					:disabled="status === 'loading'"
					@change="resetResult">
					<option
						v-for="option in comparisonOptions"
						:key="option.key"
						:value="option.key">
						{{ optionLabel(option) }}
					</option>
				</select>
			</label>
		</div>

		<NcNoteCard
			v-if="comparisonOptionState.quarantined.length"
			class="version-comparison-dialog__message"
			type="warning"
			role="status"
			:text="t('collectives', 'Some page versions could not be used for comparison.')" />
		<NcNoteCard
			v-if="sameSelection"
			class="version-comparison-dialog__message"
			type="info"
			role="status"
			:text="t('collectives', 'Select two different versions.')" />
		<NcNoteCard
			v-if="unsupportedMessage"
			class="version-comparison-dialog__message"
			type="info"
			role="status"
			:text="unsupportedMessage" />
		<NcNoteCard
			v-if="errorMessage"
			class="version-comparison-dialog__message"
			type="error"
			role="alert"
			:text="errorMessage" />
		<div v-if="status === 'loading'" class="version-comparison-dialog__loading" role="status">
			<NcLoadingIcon :size="32" />
			<span>{{ t('collectives', 'Loading versions…') }}</span>
		</div>
		<div
			ref="comparisonEl"
			class="version-comparison-dialog__comparison"
			:aria-busy="status === 'loading'" />

		<template v-if="status !== 'ready'" #actions>
			<NcButton
				v-if="status === 'error'"
				variant="primary"
				type="button"
				@click="compareSelected">
				{{ t('collectives', 'Retry') }}
			</NcButton>
			<NcButton
				v-if="status === 'idle' || status === 'loading'"
				variant="primary"
				type="submit"
				:disabled="!canCompare || status === 'loading'">
				{{ t('collectives', 'Compare') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { markRaw } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import {
	ComparisonRequestManager,
	ComparisonSnapshotError,
	createVersionComparisonState,
	CURRENT_VERSION_KEY,
	loadVersionComparisonSnapshots,
	normalizeVersionComparisonPair,
	selectVersionComparisonRenderer,
	VersionComparisonSnapshotCache,
} from '../../util/versionComparison.js'

export default {
	name: 'VersionComparisonDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcNoteCard,
	},

	props: {
		currentVersion: {
			type: Object,
			required: true,
		},

		versions: {
			type: Array,
			required: true,
		},

		filePath: {
			type: String,
			required: true,
		},

		shareToken: {
			type: String,
			default: null,
		},
	},

	setup() {
		return {
			historicalSnapshotCache: markRaw(new VersionComparisonSnapshotCache()),
			isMobile: useIsMobile(),
			requestManager: new ComparisonRequestManager(),
		}
	},

	data() {
		return {
			comparisonInstance: null,
			earlierKey: '',
			errorMessage: '',
			laterKey: '',
			open: false,
			status: 'idle',
			unsupportedMessage: '',
		}
	},

	computed: {
		comparisonOptionState() {
			return createVersionComparisonState(this.currentVersion, this.versions)
		},

		comparisonOptions() {
			return this.comparisonOptionState.options
		},

		earlier() {
			return this.comparisonOptions.find(({ key }) => key === this.earlierKey)
		},

		later() {
			return this.comparisonOptions.find(({ key }) => key === this.laterKey)
		},

		sameSelection() {
			return Boolean(this.earlierKey && this.earlierKey === this.laterKey)
		},

		canCompare() {
			return Boolean(this.earlier
				&& this.later
				&& ['current', 'historical'].includes(this.earlier.kind)
				&& ['current', 'historical'].includes(this.later.kind)
				&& !this.sameSelection)
		},
	},

	beforeUnmount() {
		this.cleanup()
		this.historicalSnapshotCache.clear()
	},

	methods: {
		t,

		comparisonDialogSize() {
			return typeof window.OCA?.Text?.createMarkdownContentComparison === 'function'
				? 'full'
				: 'large'
		},

		openSelector() {
			const historical = this.comparisonOptions.find(({ kind }) => kind === 'historical')
			if (!historical) {
				return
			}
			this.earlierKey = historical.key
			this.laterKey = CURRENT_VERSION_KEY
			this.resetResult()
			this.open = true
		},

		openFor(version) {
			const selected = this.comparisonOptions.find(({ fileVersion }) => fileVersion === String(version.fileVersion))
			if (!selected) {
				showError(t('collectives', 'This page version cannot be used for comparison.'))
				return
			}
			this.earlierKey = selected.key
			this.laterKey = CURRENT_VERSION_KEY
			this.resetResult()
			this.open = true
			this.$nextTick(this.compareSelected)
		},

		onOpenUpdate(open) {
			this.open = open
			if (!open) {
				this.cleanup()
			}
		},

		optionLabel(option) {
			const timestamp = t('collectives', '{date} at {time}', {
				date: moment(option.mtime).format('LL'),
				time: moment(option.mtime).format('LTS'),
			})
			if (option.kind === 'current') {
				return t('collectives', 'Current version — {timestamp}', { timestamp })
			}
			return option.label
				? t('collectives', '{label} — {timestamp}', { label: option.label, timestamp })
				: timestamp
		},

		async compareSelected() {
			if (!this.canCompare) {
				return
			}
			const pair = normalizeVersionComparisonPair(this.earlier, this.later)
			this.earlierKey = pair.earlier.key
			this.laterKey = pair.later.key
			this.destroyComparison()
			this.errorMessage = ''
			this.unsupportedMessage = ''

			const comparisonFactory = window.OCA?.Text?.createMarkdownContentComparison
			const renderer = selectVersionComparisonRenderer({
				comparisonFactory,
				viewerCompare: window.OCA?.Viewer?.compare,
				isMobile: this.isMobile,
			})
			if (renderer === 'unsupported') {
				this.status = 'unsupported'
				this.unsupportedMessage = this.isMobile
					? t('collectives', 'Version comparison requires a newer version of the Text app on mobile.')
					: t('collectives', 'Version comparison is not supported by the installed Text app.')
				return
			}
			if (renderer === 'viewer') {
				window.OCA.Viewer.compare(
					{ ...pair.later.fileInfo },
					{ ...pair.earlier.fileInfo },
				)
				this.onOpenUpdate(false)
				return
			}

			const request = this.requestManager.begin()
			let pendingInstance = null
			this.status = 'loading'
			try {
				const contents = await loadVersionComparisonSnapshots(
					pair,
					this.fetchSnapshot,
					request.signal,
				)
				if (!this.requestManager.isCurrent(request.generation)) {
					return
				}

				await this.$nextTick()
				const comparisonHost = document.createElement('div')
				pendingInstance = await comparisonFactory({
					...contents,
					el: comparisonHost,
					fileId: this.currentVersion.fileId,
					filePath: this.filePath,
					shareToken: this.shareToken,
					openLinkHandler: window.OCA?.Collectives?.openLink,
				})
				if (!this.requestManager.isCurrent(request.generation)) {
					pendingInstance.destroy()
					return
				}
				this.$refs.comparisonEl.replaceChildren(...comparisonHost.childNodes)
				this.comparisonInstance = markRaw(pendingInstance)
				pendingInstance = null
				this.status = 'ready'
			} catch (error) {
				pendingInstance?.destroy()
				if (!this.requestManager.isCurrent(request.generation) || this.isCancellation(error)) {
					return
				}
				this.status = 'error'
				this.errorMessage = error instanceof ComparisonSnapshotError
					? this.snapshotErrorMessage(error)
					: t('collectives', 'Could not initialize version comparison.')
			}
		},

		routeContextChanged() {
			this.open = false
			this.cleanup()
			this.historicalSnapshotCache.clear()
		},

		async fetchSnapshot(snapshot, signal) {
			return this.historicalSnapshotCache.load(snapshot, async (requestedSnapshot, requestedSignal) => {
				const config = {
					signal: requestedSignal,
					transformResponse: [(content) => content],
				}
				if (requestedSnapshot.kind === 'current') {
					config.params = { timestamp: Date.now() }
				}
				const response = await axios.get(requestedSnapshot.url, config)
				return response.data
			}, signal)
		},

		snapshotErrorMessage(error) {
			const statuses = error.reasons.map((reason) => reason?.response?.status)
			if (statuses.includes(403)) {
				return error.reasons.length === 2
					? t('collectives', 'You do not have permission to load the selected versions.')
					: t('collectives', 'You do not have permission to load one of the selected versions.')
			}
			if (statuses.some((status) => status === 404 || status === 410)) {
				return error.reasons.length === 2
					? t('collectives', 'The selected versions have expired or were removed.')
					: t('collectives', 'One of the selected versions has expired or was removed.')
			}
			return t('collectives', 'Could not load the selected versions because of a network error.')
		},

		isCancellation(error) {
			return error?.name === 'AbortError'
				|| error?.name === 'CanceledError'
				|| axios.isCancel(error)
		},

		resetResult() {
			this.destroyComparison()
			this.requestManager.cancel()
			this.errorMessage = ''
			this.unsupportedMessage = ''
			this.status = 'idle'
		},

		destroyComparison() {
			this.comparisonInstance?.destroy()
			this.comparisonInstance = null
			this.$refs.comparisonEl?.replaceChildren()
		},

		cleanup() {
			this.requestManager.cancel()
			this.destroyComparison()
		},
	},
}
</script>

<style scoped lang="scss">
// Deliberate layout maximum: keeps the side-by-side comparison readable on ultra-wide screens.
$comparison-max-inline-size: 1600px;
// Deliberate layout maximum: keeps the two version pickers close together instead of edge to edge.
$selectors-max-inline-size: 1040px;

:global(div.dialog__content.version-comparison-dialog) {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	inline-size: 100%;
	max-inline-size: $comparison-max-inline-size;
	min-height: 0;
	block-size: 100%;
	margin-inline: auto;
	overflow: hidden;
}

.version-comparison-dialog {
	&__selectors {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		align-items: end;
		flex: 0 0 auto;
		gap: calc(3 * var(--default-grid-baseline));
		inline-size: 100%;
		max-inline-size: $selectors-max-inline-size;
		margin-inline: auto;
		padding-inline: calc(3 * var(--default-grid-baseline));
		padding-block-end: calc(2 * var(--default-grid-baseline));

		select {
			display: block;
			width: 100%;
			min-height: var(--default-clickable-area);
			padding-inline: calc(3 * var(--default-grid-baseline)) calc(9 * var(--default-grid-baseline));
			border-color: var(--color-border-maxcontrast);
			border-radius: var(--border-radius-element);
			background-color: var(--color-main-background);
			font-size: var(--default-font-size);
			line-height: var(--default-line-height);
			color: var(--color-main-text);
		}
	}

	&__field {
		display: block;
		inline-size: 100%;
	}

	&__field-label {
		display: flex;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline));
		margin-block-end: var(--default-grid-baseline);
		font-size: var(--font-size-small);
		font-weight: var(--font-weight-element);
		line-height: var(--default-line-height);
		color: var(--color-text-maxcontrast);
	}

	// Decorative rank marker: muted bar for the earlier version, primary dot for the later one.
	&__indicator {
		display: block;
		flex: 0 0 auto;
		block-size: calc(0.5 * var(--default-grid-baseline));
		inline-size: calc(2.5 * var(--default-grid-baseline));
		border-radius: var(--border-radius-pill);
		background-color: var(--color-text-maxcontrast);
	}

	&__field--later {
		.version-comparison-dialog__field-label {
			color: var(--color-main-text);
		}

		.version-comparison-dialog__indicator {
			block-size: calc(2 * var(--default-grid-baseline));
			inline-size: calc(2 * var(--default-grid-baseline));
			background-color: var(--color-primary-element);
		}
	}

	&__message {
		margin-block: calc(3 * var(--default-grid-baseline));
	}

	&__loading {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: calc(2 * var(--default-grid-baseline));
		flex: 1 1 auto;
		min-height: 160px;
	}

	&__comparison {
		display: flex;
		box-sizing: border-box;
		flex: 1 1 auto;
		inline-size: 100%;
		min-inline-size: 0;
		min-height: 0;
		overflow: hidden;

		&:not(:empty) {
			border-block-start: 1px solid var(--color-border);
		}
	}
}

@media (max-width: 600px) {
	.version-comparison-dialog__selectors {
		grid-template-columns: 1fr;
	}
}
</style>
