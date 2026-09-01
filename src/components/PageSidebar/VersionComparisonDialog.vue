<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:open="open"
		isForm
		:size="dialogSize"
		contentClasses="version-comparison-dialog"
		:name="t('collectives', 'Compare versions')"
		@submit="compareSelected"
		@update:open="onOpenUpdate">
		<div class="version-comparison-dialog__selectors">
			<label
				v-for="side in ['earlier', 'later']"
				:key="side"
				class="field"
				:class="{ 'field--later': side === 'later' }">
				<span class="field-label">
					<span class="indicator" aria-hidden="true" />
					{{ side === 'earlier' ? t('collectives', 'Earlier') : t('collectives', 'Later') }}
				</span>
				<select
					v-model="selection[side]"
					:disabled="status === 'loading'"
					@change="onSelectionChange">
					<option
						v-for="option in displayed"
						:key="option.key"
						:value="option.key">
						{{ optionLabel(option) }}
					</option>
				</select>
			</label>
			<NcButton
				v-if="canCopy"
				class="copy"
				variant="tertiary"
				type="button"
				@click="copyComparisonLink">
				<template #icon>
					<ContentCopyIcon :size="20" />
				</template>
				{{ t('collectives', 'Copy comparison link') }}
			</NcButton>
		</div>

		<NcNoteCard
			v-if="optionState.quarantinedCount"
			class="message"
			type="warning"
			role="status"
			:text="t('collectives', 'Some page versions could not be used for comparison.')" />
		<NcNoteCard
			v-if="selection.earlier && selection.earlier === selection.later"
			class="message"
			type="info"
			role="status"
			:text="t('collectives', 'Select two different versions.')" />
		<NcNoteCard
			v-if="message"
			class="message"
			:type="status === 'unsupported' ? 'info' : 'error'"
			:role="status === 'unsupported' ? 'status' : 'alert'"
			:text="message" />
		<section
			v-if="previews.length"
			class="previews"
			:aria-label="t('collectives', 'Bounded version previews')">
			<article v-for="snapshot in previews" :key="snapshot.key" class="version-comparison-dialog__preview">
				<strong>{{ optionLabel(snapshot) }}</strong>
				<p v-if="snapshot.previewError" role="status">
					{{ t('collectives', 'Could not load a preview for this version.') }}
				</p>
				<pre v-else>{{ snapshot.preview }}</pre>
				<div class="preview-actions">
					<a :href="snapshot.url" target="_blank" rel="noopener noreferrer">
						{{ t('collectives', 'Open version') }}
					</a>
					<a :href="snapshot.url" download>
						{{ t('collectives', 'Download version') }}
					</a>
				</div>
			</article>
		</section>
		<div v-if="status === 'loading'" class="version-comparison-dialog__loading" role="status">
			<NcLoadingIcon :size="32" />
			<span>{{ t('collectives', 'Loading versions…') }}</span>
		</div>
		<div
			ref="host"
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

<script setup>
/* eslint-disable jsdoc/require-jsdoc */
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import { useVersionsStore } from '../../stores/versions.js'
import {
	classifyComparisonSnapshotError as classifyError,
	createComparisonRequestManager as createRequests,
	createVersionComparisonState as createState,
	CURRENT_VERSION_KEY as CURRENT,
	isVersionComparisonCancellation as isCancellation,
	ComparisonSnapshotLimitError as LimitError,
	loadBoundedSnapshotBody as loadBody,
	loadBoundedSnapshotPreview as loadPreview,
	loadVersionComparisonSnapshots as loadSnapshots,
	normalizeVersionComparisonPair as normalizePair,
	CurrentSnapshotPreparationError as PreparationError,
	prepareVersionComparisonPair as preparePair,
	selectVersionComparisonRenderer as selectRenderer,
	VersionComparisonSnapshotCache as SnapshotCache,
	ComparisonSnapshotError as SnapshotError,
} from '../../util/versionComparison.js'
import {
	COMPARE_FROM_QUERY as FROM,
	COMPARISON_HISTORY_STATE as HISTORY,
	parseVersionComparisonRoute as parseRoute,
	resolveVersionComparisonRoute as resolveRoute,
	COMPARE_TO_QUERY as TO,
	withoutVersionComparisonRoute as withoutRoute,
	withVersionComparisonRoute as withRoute,
} from '../../util/versionComparisonRoute.js'

const props = defineProps({
	currentVersion: { type: Object, required: true },
	versions: { type: Array, required: true },
	filePath: { type: String, required: true },
})
const emit = defineEmits(['retryRoute'])
const route = useRoute()
const router = useRouter()
const versionsStore = useVersionsStore()
const isMobile = useIsMobile()
const cache = new SnapshotCache()
const requests = createRequests()
const host = ref(null)
const selection = reactive({ earlier: '', later: '' })
const previews = ref([])
const message = ref('')
const missing = ref([])
const open = ref(false)
const routeTask = ref(null)
const status = ref('idle')
const shown = ref('')
let comparison = null
let opener = null

const optionState = computed(() => createState(props.currentVersion, props.versions))
const options = computed(() => optionState.value.options)
const displayed = computed(() => [...options.value, ...missing.value])
const earlier = computed(() => displayed.value.find(({ key }) => key === selection.earlier))
const later = computed(() => displayed.value.find(({ key }) => key === selection.later))
const canCompare = computed(() => [earlier.value, later.value].every((option) => ['current', 'historical'].includes(option?.kind)) && selection.earlier !== selection.later)
const canCopy = computed(() => {
	const parsed = parseRoute(route.query)
	return status.value === 'ready' && shown.value && parsed.kind === 'valid' && pairKey(parsed.from, parsed.to) === shown.value
})
const dialogSize = ref('large')

onBeforeUnmount(() => cleanup())

function openSelector(source) {
	const historical = options.value.find(({ kind }) => kind === 'historical')
	if (historical) {
		openManual(historical, source)
	}
}
function openFor(version, source) {
	const selected = options.value.find(({ fileVersion }) => fileVersion === String(version.fileVersion))
	if (!selected) {
		showError(t('collectives', 'This page version cannot be used for comparison.'))
		return
	}
	openManual(selected, source)
	nextTick(compareSelected)
}
function openManual(selected, source = document.activeElement) {
	opener = source
	selection.earlier = selected.key
	selection.later = CURRENT
	resetResult()
	open.value = true
}
async function openRoutedPair(pair) {
	const signature = pairKey(pair.from, pair.to)
	const active = status.value === 'ready' ? shown.value : routeTask.value && pairKey(routeTask.value.from, routeTask.value.to)
	if (open.value && ['idle', 'loading', 'ready'].includes(status.value) && signature === active) {
		return
	}
	resetResult()
	open.value = true
	routeTask.value = { ...pair }
	const ambiguous = new Set(optionState.value.ambiguousRouteIds)
	const resolved = resolveRoute(options.value, pair)
	missing.value = resolved.missing.map((routeId) => ({
		key: `${ambiguous.has(routeId) ? 'ambiguous' : 'missing'}:${routeId}`,
		kind: ambiguous.has(routeId) ? 'ambiguous' : 'missing',
		label: ambiguous.has(routeId)
			? t('collectives', 'Ambiguous version')
			: t('collectives', 'Unavailable version ({version})', { version: routeId }),
		routeId,
	}))
	const optionFor = (routeId, resolvedOption) => resolvedOption ?? missing.value.find((option) => option.routeId === routeId)
	selection.earlier = optionFor(pair.from, resolved.first).key
	selection.later = optionFor(pair.to, resolved.second).key

	if (resolved.missing.some((routeId) => ambiguous.has(routeId))) {
		fail(t('collectives', 'The version comparison link is ambiguous and could not be opened.'))
	} else if (resolved.missing.length) {
		fail(resolved.missing.length === 2
			? t('collectives', 'The selected versions have expired or were removed.')
			: t('collectives', 'One of the selected versions has expired or was removed.'))
	} else if (!resolved.invalid) {
		open.value = comparisonRenderer() !== 'viewer'
		await nextTick()
		await compareSelected()
	}
}
function fail(failureMessage) {
	status.value = 'error'
	message.value = failureMessage
}
async function onOpenUpdate(value) {
	open.value = value
	if (value) {
		return
	}
	cleanup()
	if (isManagedHistoryEntry()) {
		await new Promise((resolve) => {
			const remove = router.afterEach(() => (remove(), resolve()))
			router.back()
		})
	} else if (parseRoute(route.query).kind === 'valid') {
		await removeComparisonRoute()
	}
	await nextTick()
	opener?.focus()
}
function optionLabel(option) {
	if (['missing', 'ambiguous'].includes(option.kind)) {
		return option.label
	}
	const timestamp = t('collectives', '{date} at {time}', { date: moment(option.mtime).format('LL'), time: moment(option.mtime).format('LTS') })
	if (option.kind === 'current') {
		return t('collectives', 'Current version — {timestamp}', { timestamp })
	}
	return option.label ? t('collectives', '{label} — {timestamp}', { label: option.label, timestamp }) : timestamp
}
async function compareSelected() {
	if (!canCompare.value) {
		return
	}
	let pair = normalizePair(earlier.value, later.value)
	selection.earlier = pair.earlier.key
	selection.later = pair.later.key
	destroyComparison()
	previews.value = []
	message.value = ''
	status.value = 'loading'

	const factory = window.OCA?.Text?.createMarkdownContentComparison
	const renderer = comparisonRenderer()
	dialogSize.value = renderer === 'semantic' ? 'full' : 'large'
	if (renderer === 'unsupported') {
		status.value = 'unsupported'
		message.value = isMobile.value
			? t('collectives', 'Version comparison requires a newer version of the Text app on mobile.')
			: t('collectives', 'Version comparison is not supported by the installed Text app.')
		return
	}
	const request = requests.begin()
	let pending = null
	let expiredSelectionCount = 0
	try {
		pair = await preparePair(
			pair,
			() => versionsStore.prepareCurrentSnapshot(),
			(versions) => {
				const options = createState(props.currentVersion, versions).options
				const earlier = options.find(({ key }) => key === pair.earlier.key)
				const later = options.find(({ key }) => key === pair.later.key)
				expiredSelectionCount = Number(!earlier) + Number(!later)
				return expiredSelectionCount ? pair : normalizePair(earlier, later)
			},
		)
		if (!requests.isCurrent(request.generation)) {
			return
		}
		if (expiredSelectionCount) {
			fail(expiredSelectionCount === 2
				? t('collectives', 'The selected versions have expired or were removed.')
				: t('collectives', 'One of the selected versions has expired or was removed.'))
			return
		}
		if (renderer === 'viewer') {
			closeDialog()
			await nextTick()
			window.OCA.Viewer.compare({ ...pair.later.fileInfo }, { ...pair.earlier.fileInfo })
			return
		}
		const contents = await loadSnapshots(pair, fetchSnapshot, request.signal)
		if (!requests.isCurrent(request.generation)) {
			return
		}
		await nextTick()
		const mount = document.createElement('div')
		pending = await factory({
			...contents,
			el: mount,
			fileId: props.currentVersion.fileId,
			filePath: props.filePath,
			openLinkHandler: window.OCA?.Collectives?.openLink,
		})
		if (typeof pending?.destroy !== 'function') {
			throw new TypeError('Comparison factory returned an invalid instance')
		}
		if (!requests.isCurrent(request.generation)) {
			destroyInstance(pending)
			pending = null
			return
		}
		host.value.replaceChildren(...mount.childNodes)
		comparison = pending
		pending = null
		status.value = 'ready'
		try {
			await routeSuccessfulPair(pair)
		} catch {
			shown.value = ''
			showError(t('collectives', 'Could not update the comparison link.'))
		}
	} catch (error) {
		destroyInstance(pending)
		if (!requests.isCurrent(request.generation) || isCancellation(error)) {
			return
		}
		open.value = true
		await nextTick()
		try {
			await showComparisonFailure(error, request)
		} catch (failureError) {
			if (requests.isCurrent(request.generation) && !isCancellation(failureError)) {
				throw failureError
			}
		}
	}
}
function comparisonRenderer() {
	return selectRenderer({ comparisonFactory: window.OCA?.Text?.createMarkdownContentComparison, viewerCompare: window.OCA?.Viewer?.compare, isMobile: isMobile.value })
}
function onSelectionChange() {
	resetResult()
	void removeComparisonRoute(true)
}
function retryComparison() {
	return routeTask.value && missing.value.length ? emit('retryRoute') : compareSelected()
}
async function routeSuccessfulPair(pair) {
	const from = pair.earlier.routeId
	const to = pair.later.routeId
	if ([from, to].some((routeId) => optionState.value.ambiguousRouteIds.includes(routeId))) {
		shown.value = ''
		return
	}
	const signature = pairKey(from, to)
	shown.value = signature
	const parsed = parseRoute(route.query)
	if (parsed.kind === 'valid' && pairKey(parsed.from, parsed.to) === signature) {
		return
	}
	const location = withRoute(route, from, to)
	const managed = isManagedHistoryEntry()
	if (routeTask.value || parsed.kind === 'valid' || managed) {
		await router.replace({ ...location, state: { [HISTORY]: managed } })
	} else {
		await router.push({ ...location, state: { [HISTORY]: true } })
	}
}
async function removeComparisonRoute(preserveManaged = false) {
	if (!Object.hasOwn(route.query, FROM) && !Object.hasOwn(route.query, TO)) {
		return
	}
	await router.replace({
		...withoutRoute(route),
		state: { [HISTORY]: preserveManaged && isManagedHistoryEntry() },
	})
}
function closeFromRoute() {
	if (shown.value || routeTask.value) {
		closeDialog()
	}
}
function closeDialog() {
	open.value = false
	cleanup()
}
function isManagedHistoryEntry() {
	return window.history.state?.[HISTORY] === true
}
function pairKey(from, to) {
	return `${from}\u0000${to}`
}
async function copyComparisonLink() {
	if (!canCopy.value) {
		return
	}
	const url = new URL(router.resolve(route.fullPath).href, window.location.origin).href
	try {
		await navigator.clipboard.writeText(url)
		showSuccess(t('collectives', 'Comparison link copied'))
	} catch {
		showError(t('collectives', 'Could not copy the comparison link.'))
	}
}
function fetchSnapshot(snapshot, signal) {
	return cache.load(snapshot, (item, itemSignal) => loadBody(item, fetch, itemSignal), signal)
}
async function showComparisonFailure(error, request) {
	const limited = error instanceof LimitError ? error.snapshots : null
	const failureMessage = limited
		? t('collectives', 'The selected version is too large for complete comparison. A bounded preview is shown instead.')
		: error instanceof SnapshotError
			? snapshotErrorMessage(error)
			: error instanceof PreparationError
				? t('collectives', 'Could not save current changes before comparison. Please try again.')
				: t('collectives', 'Could not initialize version comparison.')
	const loaded = await Promise.all((limited ?? []).map(async (snapshot) => {
		try {
			const { content: preview } = await loadPreview(snapshot, fetch, request.signal)
			return { ...snapshot, preview, previewError: false }
		} catch (previewError) {
			if (isCancellation(previewError)) {
				throw previewError
			}
			return { ...snapshot, preview: '', previewError: true }
		}
	}))
	if (requests.isCurrent(request.generation)) {
		previews.value = loaded
		fail(failureMessage)
	}
}
function snapshotErrorMessage(error) {
	return {
		'permission-one': t('collectives', 'You do not have permission to load one of the selected versions.'),
		'permission-two': t('collectives', 'You do not have permission to load the selected versions.'),
		'expired-one': t('collectives', 'One of the selected versions has expired or was removed.'),
		'expired-two': t('collectives', 'The selected versions have expired or were removed.'),
		encoding: t('collectives', 'One of the selected versions contains invalid UTF-8 text.'),
		network: t('collectives', 'Could not load the selected versions because of a network error.'),
	}[classifyError(error)]
}
function resetResult() {
	destroyComparison()
	requests.cancel()
	previews.value = []
	message.value = ''
	missing.value = []
	routeTask.value = null
	shown.value = ''
	status.value = 'idle'
	dialogSize.value = 'large'
}
function destroyComparison() {
	const instance = comparison
	comparison = null
	host.value?.replaceChildren()
	destroyInstance(instance)
}
function destroyInstance(instance) {
	try {
		instance?.destroy()
	} catch {
		showError(t('collectives', 'Could not clean up version comparison.'))
	}
}
function cleanup() {
	resetResult()
	cache.clear()
}
defineExpose({ closeDialog, closeFromRoute, openFor, openRoutedPair, openSelector })
</script>

<style scoped lang="scss">
$g: var(--default-grid-baseline);

:global(div.dialog__content.version-comparison-dialog) {
	display: flex;
	flex-direction: column;
	max-inline-size: 1600px;
	block-size: 100%;
	margin-inline: auto;
	overflow: hidden;
}

.version-comparison-dialog {
	&__selectors {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr)) auto;
		align-items: end;
		flex: none;
		gap: calc(3 * $g);
		inline-size: min(100%, 1040px);
		margin-inline: auto;
		padding: 0 calc(3 * $g) calc(2 * $g);
		select {
			display: block;
			box-sizing: border-box;
			inline-size: 100%;
			block-size: var(--default-clickable-area);
			margin: 0;
		}
	}
	.field-label {
		display: flex;
		align-items: center;
		gap: calc(2 * $g);
		margin-block-end: $g;
		font-weight: var(--font-weight-element);
	}
	.indicator {
		inline-size: calc(2 * $g);
		block-size: calc(2 * $g);
		border-radius: 50%;
		background: var(--color-text-maxcontrast);
	}
	.field--later .indicator {
		background: var(--color-primary-element);
	}
	.copy {
		align-self: end;
		justify-self: end;
		white-space: nowrap;
	}
	.message {
		margin-block: calc(3 * $g);
	}
	&__loading {
		display: grid;
		flex: 1;
		min-block-size: 160px;
		place-content: center;
	}
	.previews {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
		gap: calc(2 * $g);
		min-block-size: 0;
		overflow: auto;
	}
	&__preview {
		min-inline-size: 0;
		pre {
			max-block-size: 16rem;
			overflow: auto;
			white-space: pre-wrap;
		}
	}
	&__comparison {
		display: flex;
		flex: 1;
		min-inline-size: 0;
		min-block-size: 0;
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
	.copy {
		grid-column: 1 / -1;
	}
}
@media (max-width: 600px) {
	.version-comparison-dialog__selectors {
		grid-template-columns: 1fr;
	}
	.copy {
		grid-column: auto;
	}
}
</style>
