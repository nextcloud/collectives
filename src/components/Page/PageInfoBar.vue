<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="text-menubar">
		<a v-if="currentPage.lastUserId" class="infobar-item infobar-lastupdate" @click="emitSidebar('versions')">
			<div class="item-text">
				<LastUserBubble
					:last-user-id="currentPage.lastUserId"
					:last-user-display-name="currentPage.lastUserDisplayName"
					:timestamp="currentPage.timestamp"
					:show-prefix-string="!isMobile" />
			</div>
		</a>
		<template v-if="attachmentCount">
			<div v-if="currentPage.lastUserId" class="infobar-seperator">
				•
			</div>
			<a class="infobar-item" @click="emitSidebar('attachments')">
				<div class="item-icon">
					<PaperclipIcon :size="18" />
				</div>
				<div class="item-text">
					{{ attachmentCountString }}
				</div>
			</a>
		</template>
		<template v-if="backlinkCount">
			<div v-if="currentPage.lastUserId || attachmentCount" class="infobar-seperator">
				•
			</div>
			<a class="infobar-item" @click="emitSidebar('backlinks')">
				<div class="item-icon">
					<ArrowBottomLeftIcon :size="18" />
				</div>
				<div class="item-text">
					{{ backlinkCountString }}
				</div>
			</a>
		</template>
	</div>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { n, t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import ArrowBottomLeftIcon from 'vue-material-design-icons/ArrowBottomLeft.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import LastUserBubble from '../LastUserBubble.vue'

export default {
	name: 'PageInfoBar',

	components: {
		ArrowBottomLeftIcon,
		LastUserBubble,
		PaperclipIcon,
	},

	props: {
		currentPage: {
			type: Object,
			required: true,
		},

		attachmentCount: {
			type: Number,
			required: true,
		},

		backlinkCount: {
			type: Number,
			required: true,
		},
	},

	setup() {
		const isMobile = useIsMobile()
		return { isMobile }
	},

	computed: {
		attachmentCountString() {
			return this.isMobile
				? this.attachmentCount
				: n('collectives', '%n attachment', '%n attachments', this.attachmentCount)
		},

		backlinkCountString() {
			return this.isMobile
				? this.backlinkCount
				: n('collectives', '%n backlink', '%n backlinks', this.backlinkCount)
		},
	},

	methods: {
		t,
		emitSidebar(tab) {
			emit('collectives:page-sidebar', { open: true, tab })
		},
	},
}
</script>

<style scoped lang="scss">
.text-menubar {
	// Copied from `.text-menubar` in text app
	--background-blur: blur(10px);
	position: sticky;
	top: 0;
	bottom: var(--default-grid-baseline);
	width: 100%;
	z-index: 10021;
	background-color: var(--color-main-background-translucent);
	backdrop-filter: var(--background-blur);
	height: var(--default-clickable-area);
	border-bottom: 1px solid var(--color-border);
	padding-block: var(--default-grid-baseline);
	display: flex;
	align-items: center;

	overflow: hidden;
}

.infobar-item {
	display: flex;
	flex-direction: row;
	flex-wrap: nowrap;
	align-items: center;
	overflow: hidden;
	white-space: nowrap;
	opacity: .5;
}

.infobar-lastupdate {
	padding-inline-start: 14px;
}

.infobar-seperator {
	padding: 0px 12px;
	opacity: .5;
}

.item-text {
	overflow: hidden;
	text-overflow: ellipsis;
}

@media print {
	.text-menubar {
		display: none !important;
	}
}
</style>
