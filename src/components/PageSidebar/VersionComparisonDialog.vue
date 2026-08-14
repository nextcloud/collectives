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
					@change="onSelectionChange">
					<option
						v-for="option in comparisonOptionsForDisplay"
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
					@change="onSelectionChange">
					<option
						v-for="option in comparisonOptionsForDisplay"
						:key="option.key"
						:value="option.key">
						{{ optionLabel(option) }}
					</option>
				</select>
			</label>
			<NcButton
				v-if="canCopyComparisonLink"
				class="version-comparison-dialog__copy"
				variant="secondary"
				type="button"
				@click="copyComparisonLink">
				<template #icon>
					<ContentCopyIcon :size="20" />
				</template>
				{{ t('collectives', 'Copy comparison link') }}
			</NcButton>
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
				@click="retryComparison">
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { markRaw } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
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
import {
	COMPARE_FROM_QUERY,
	COMPARE_TO_QUERY,
	COMPARISON_HISTORY_STATE,
	parseVersionComparisonRoute,
	resolveVersionComparisonRoute,
	withoutVersionComparisonRoute,
	withVersionComparisonRoute,
} from '../../util/versionComparisonRoute.js'

export default {
	name: 'VersionComparisonDialog',

	components: {
		ContentCopyIcon,
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

	emits: ['retryRoute'],

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
			missingOptions: [],
			open: false,
			openMode: 'none',
			requestedRoute: null,
			status: 'idle',
			successfulRouteSignature: '',
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

		comparisonOptionsForDisplay() {
			return [...this.comparisonOptions, ...this.missingOptions]
		},

		earlier() {
			return this.comparisonOptionsForDisplay.find(({ key }) => key === this.earlierKey)
		},

		later() {
			return this.comparisonOptionsForDisplay.find(({ key }) => key === this.laterKey)
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

		canCopyComparisonLink() {
			if (this.status !== 'ready' || !this.successfulRouteSignature) {
				return false
			}
			const route = parseVersionComparisonRoute(this.$route.query)
			return route.kind === 'valid'
				&& this.pairSignature(route.from, route.to) === this.successfulRouteSignature
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
			this.missingOptions = []
			this.openMode = 'manual'
			this.requestedRoute = null
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
			this.missingOptions = []
			this.openMode = 'manual'
			this.requestedRoute = null
			this.earlierKey = selected.key
			this.laterKey = CURRENT_VERSION_KEY
			this.resetResult()
			this.open = true
			this.$nextTick(this.compareSelected)
		},

		async openRoutedPair(requested) {
			const requestedSignature = this.pairSignature(requested.from, requested.to)
			if (this.open
				&& this.status === 'ready'
				&& requestedSignature === this.successfulRouteSignature) {
				this.openMode = 'route'
				return
			}
			if (this.open
				&& (this.status === 'idle' || this.status === 'loading')
				&& this.requestedRoute
				&& requestedSignature === this.pairSignature(this.requestedRoute.from, this.requestedRoute.to)) {
				return
			}

			this.resetResult()
			this.missingOptions = []
			this.open = true
			this.openMode = 'route'
			this.requestedRoute = { ...requested }

			const ambiguousRouteIds = new Set(this.comparisonOptionState.ambiguousRouteIds)
			const resolved = resolveVersionComparisonRoute(
				this.comparisonOptions,
				requested,
				ambiguousRouteIds,
			)
			this.missingOptions = resolved.missing.map((routeId) => ({
				fileInfo: { basename: routeId },
				fileVersion: routeId,
				key: `${ambiguousRouteIds.has(routeId) ? 'ambiguous' : 'missing'}:${routeId}`,
				kind: ambiguousRouteIds.has(routeId) ? 'ambiguous' : 'missing',
				label: ambiguousRouteIds.has(routeId)
					? t('collectives', 'Ambiguous version')
					: t('collectives', 'Unavailable version ({version})', { version: routeId }),
				mtime: 0,
				routeId,
			}))
			const optionFor = (routeId, resolvedOption) => resolvedOption
				?? this.missingOptions.find((option) => option.routeId === routeId)
			this.earlierKey = optionFor(requested.from, resolved.first).key
			this.laterKey = optionFor(requested.to, resolved.second).key

			if (resolved.missing.some((routeId) => ambiguousRouteIds.has(routeId))) {
				this.status = 'error'
				this.errorMessage = t('collectives', 'The version comparison link is ambiguous and could not be opened.')
				return
			}

			if (resolved.missing.length) {
				this.status = 'error'
				this.errorMessage = resolved.missing.length === 2
					? t('collectives', 'The selected versions have expired or were removed.')
					: t('collectives', 'One of the selected versions has expired or was removed.')
				return
			}

			await this.$nextTick()
			await this.compareSelected({ routeDriven: true })
		},

		async onOpenUpdate(open) {
			this.open = open
			if (open) {
				return
			}

			this.cleanup()
			const comparisonRoute = parseVersionComparisonRoute(this.$route.query)
			if (this.isManagedHistoryEntry()) {
				this.$router.back()
			} else if (comparisonRoute.kind === 'valid') {
				await this.removeComparisonRoute()
			}
			this.clearRouteState()
		},

		optionLabel(option) {
			if (option.kind === 'missing') {
				return option.label
			}
			if (option.kind === 'ambiguous') {
				return option.label
			}
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

		async compareSelected(eventOrOptions = {}) {
			if (!this.canCompare) {
				return
			}
			const routeDriven = eventOrOptions?.routeDriven === true
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
				await this.removeComparisonRoute()
				this.closeWithoutRouteAction()
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
				try {
					await this.routeSuccessfulPair(pair, routeDriven)
				} catch {
					this.successfulRouteSignature = ''
					showError(t('collectives', 'Could not update the comparison link.'))
				}
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

		onSelectionChange() {
			this.openMode = 'manual'
			this.requestedRoute = null
			this.resetResult()
			this.removeComparisonRoute(true)
		},

		async retryComparison() {
			if (this.requestedRoute && this.missingOptions.length) {
				this.$emit('retryRoute')
				return
			}
			await this.compareSelected({ routeDriven: this.openMode === 'route' })
		},

		async routeSuccessfulPair(pair, routeDriven) {
			const from = pair.earlier.routeId
			const to = pair.later.routeId
			if ([from, to].some((routeId) => this.comparisonOptionState.ambiguousRouteIds.includes(routeId))) {
				this.successfulRouteSignature = ''
				return
			}
			const signature = this.pairSignature(from, to)
			this.successfulRouteSignature = signature
			const currentRoute = parseVersionComparisonRoute(this.$route.query)
			if (currentRoute.kind === 'valid'
				&& this.pairSignature(currentRoute.from, currentRoute.to) === signature) {
				this.openMode = 'route'
				return
			}

			const location = withVersionComparisonRoute(this.$route, from, to)
			if (routeDriven || currentRoute.kind === 'valid' || this.isManagedHistoryEntry()) {
				await this.$router.replace({
					...location,
					state: { [COMPARISON_HISTORY_STATE]: this.isManagedHistoryEntry() },
				})
			} else {
				await this.$router.push({
					...location,
					state: { [COMPARISON_HISTORY_STATE]: true },
				})
			}
			this.openMode = 'route'
		},

		async removeComparisonRoute(preserveManaged = false) {
			const hasComparisonQuery = Object.hasOwn(this.$route.query, COMPARE_FROM_QUERY)
				|| Object.hasOwn(this.$route.query, COMPARE_TO_QUERY)
			if (!hasComparisonQuery) {
				return
			}
			await this.$router.replace({
				...withoutVersionComparisonRoute(this.$route),
				state: {
					[COMPARISON_HISTORY_STATE]: preserveManaged && this.isManagedHistoryEntry(),
				},
			})
		},

		closeFromRoute() {
			if (this.openMode !== 'route') {
				return
			}
			this.closeWithoutRouteAction()
		},

		closeWithoutRouteAction() {
			this.open = false
			this.cleanup()
			this.clearRouteState()
		},

		routeContextChanged() {
			this.open = false
			this.cleanup()
			this.historicalSnapshotCache.clear()
			this.clearRouteState()
		},

		clearRouteState() {
			this.missingOptions = []
			this.openMode = 'none'
			this.requestedRoute = null
			this.successfulRouteSignature = ''
		},

		isManagedHistoryEntry() {
			return window.history.state?.[COMPARISON_HISTORY_STATE] === true
		},

		pairSignature(from, to) {
			return `${from}\u0000${to}`
		},

		async copyComparisonLink() {
			if (!this.canCopyComparisonLink) {
				return
			}
			const url = new URL(
				this.$router.resolve(this.$route.fullPath).href,
				window.location.origin,
			).href
			try {
				await navigator.clipboard.writeText(url)
				showSuccess(t('collectives', 'Comparison link copied'))
			} catch {
				showError(t('collectives', 'Could not copy the comparison link.'))
			}
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
			this.successfulRouteSignature = ''
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
		grid-template-columns: repeat(2, minmax(0, 1fr)) auto;
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

	&__copy {
		align-self: end;
		white-space: nowrap;
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

@media (max-width: 900px) {
	.version-comparison-dialog__selectors {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.version-comparison-dialog__copy {
		grid-column: 1 / -1;
		justify-self: end;
	}
}

@media (max-width: 600px) {
	.version-comparison-dialog__selectors {
		grid-template-columns: 1fr;
	}

	.version-comparison-dialog__copy {
		grid-column: auto;
	}
}
</style>
