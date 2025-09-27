<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="placeholder-main">
		<!-- Placeholder animation -->
		<template v-for="(suffix, gradientIndex) in ['-regular', '-reverse']">
			<svg :key="'gradient' + suffix" :class="'placeholder-gradient placeholder-gradient' + suffix">
				<defs>
					<linearGradient :id="'placeholder-gradient-' + uniqueId + suffix">
						<stop offset="0%" :stop-color="(gradientIndex === 0) ? colorPlaceholderLight : colorPlaceholderDark" />
						<stop offset="100%" :stop-color="(gradientIndex === 0) ? colorPlaceholderDark : colorPlaceholderLight" />
					</linearGradient>
				</defs>
			</svg>

			<ul :key="'list' + suffix" :class="'placeholder-list placeholder-list' + suffix + ' placeholder-list-' + type">
				<li v-for="(width, index) in placeholderData" :key="'placeholder' + suffix + index">
					<svg
						v-if="type === 'items' || type === 'members-list'"
						:class="`${type}-placeholder`"
						xmlns="http://www.w3.org/2000/svg"
						:fill="'url(#placeholder-gradient-' + uniqueId + suffix + ')'">
						<circle :class="`${type}-placeholder-icon`" />
						<rect :class="`${type}-placeholder-line-one`" :style="width" />
					</svg>
					<svg
						v-if="type === 'page-heading'"
						class="page-heading-placeholder"
						xmlns="http://www.w3.org/2000/svg"
						:fill="'url(#placeholder-gradient-' + uniqueId + suffix + ')'">
						<circle class="page-heading-placeholder-icon" />
						<rect class="page-heading-placeholder-line-one" :style="width" />
						<rect class="page-heading-placeholder-line-two" />
					</svg>
					<svg
						v-if="type === 'text'"
						class="text-placeholder"
						xmlns="http://www.w3.org/2000/svg"
						:fill="'url(#placeholder-gradient-' + uniqueId + suffix + ')'">
						<rect class="text-placeholder-line-one" :style="textPlaceholderData[0]" />
						<rect class="text-placeholder-line-two" :style="textPlaceholderData[1]" />
						<rect class="text-placeholder-line-three" :style="textPlaceholderData[2]" />
						<rect class="text-placeholder-line-four" :style="textPlaceholderData[3]" />
					</svg>
					<svg
						v-if="type === 'avatar'"
						class="avatar-placeholder"
						xmlns="http://www.w3.org/2000/svg"
						:fill="'url(#placeholder-gradient-' + uniqueId + suffix + ')'">
						<circle class="avatar-placeholder-icon" />
					</svg>
				</li>
			</ul>
		</template>
	</div>
</template>

<script>
import uniqueIdMixin from '../mixins/uniqueIdMixin.js'
const bodyStyles = window.getComputedStyle(document.body)
const colorPlaceholderDark = bodyStyles.getPropertyValue('--color-placeholder-dark')
const colorPlaceholderLight = bodyStyles.getPropertyValue('--color-placeholder-light')

export default {
	name: 'SkeletonLoading',

	mixins: [uniqueIdMixin],

	props: {
		type: {
			type: String,
			required: true,
		},

		count: {
			type: Number,
			default: 5,
		},
	},

	setup() {
		return {
			colorPlaceholderDark,
			colorPlaceholderLight,
		}
	},

	computed: {
		placeholderData() {
			const data = []
			for (let i = 0; i < this.count; i++) {
				// generate random widths
				data.push('width: ' + (Math.floor(Math.random() * 40) + 50) + '%')
			}
			return data
		},

		textPlaceholderData() {
			const data = []
			for (let i = 0; i < 4; i++) {
				// generate random widths
				data.push('width: ' + (Math.floor(Math.random() * 50) + 60) + '%')
			}
			return data
		},
	},
}
</script>

<style lang="scss" scoped>
$clickable-area: calc(var(--default-clickable-area) - 4px);
$margin: 8px;
$messages-list-max-width: 80ch;

.placeholder-main {
	max-width: $messages-list-max-width;
	position: relative;
	margin-bottom: auto;
}

.placeholder-list {
	position: absolute;
	transform: translateZ(0);
}

.placeholder-list-regular {
	animation: pulse 2s;
	animation-iteration-count: infinite;
	animation-timing-function: linear;
}

.placeholder-list-reverse {
	animation: pulse-reverse 2s;
	animation-iteration-count: infinite;
	animation-timing-function: linear;
}

.placeholder-list-avatar {
	display: flex;
	gap: 12px;
}

.placeholder-gradient {
	position: fixed;
	height: 0;
	width: 0;
	z-index: -1;
}

.items-placeholder,
.members-list-placeholder,
.text-placeholder,
.page-heading-placeholder {

	&-icon {
		width: $clickable-area;
		height: $clickable-area;
		cx: calc($clickable-area / 2);
		cy: calc($clickable-area / 2);
		r: calc($clickable-area / 2);
	}
}

.items-placeholder,
.members-list-placeholder {
	width: calc(100% - 2 * $margin);
	height: $clickable-area;
	margin: 2px 0 -1px 0;

	&-line-one {
		width: calc(100% - $margin + $clickable-area);
		position: relative;
		height: 1.5em;
		x: calc($margin + $clickable-area);
		y: 6px;
	}
}

.members-list-placeholder {
	$icon-size: var(--default-clickable-area);
	height: calc($icon-size + 8px);

	&-icon {
		width: $icon-size;
		height: $icon-size;
		cx: calc(($icon-size + 8px) / 2);
		cy: calc(($icon-size + 8px) / 2);
		r: calc($icon-size / 2);

	}

	&-line-one {
		x: 60px;
		y: 16px;
	}
}

.avatar-placeholder {
	$icon-size: var(--default-clickable-area);
	height: $icon-size;
	width: $icon-size;

	&-icon {
		width: $icon-size;
		height: $icon-size;
		cx: calc($icon-size / 2);
		cy: calc($icon-size / 2);
		r: calc($icon-size / 2);
	}
}

.page-heading-placeholder {
	width: min($messages-list-max-width, 100vw);
	margin: 12px 14px;
	display: block;

	&-line-one {
		width: min($messages-list-max-width, 100vw);
		position: relative;
		height: 2em;
		x: calc($margin + 4px + $clickable-area);
		y: 2px;
	}

	&-line-two {
		width: 30%;
		position: relative;
		height: 1.5em;
		x: 0;
		y: 56px;
	}
}

.text-placeholder {
	width: min($messages-list-max-width, 100vw);
	height: 6em;
	margin: $margin auto;
	padding: 4px 8px 0 14px;
	display: block;

	&-line-one,
	&-line-two,
	&-line-three,
	&-line-four {
		width: 80ch;
		height: 1em;
	}

	&-line-one {
		y: 0.33em;
		width: 175px;
	}

	&-line-two {
		y: 1.66em;
	}

	&-line-three {
		y: 3em;
	}

	&-line-four {
		y: 4.33em;
	}
}

@keyframes pulse {
	0% {
		opacity: 1;
	}
	50% {
		opacity: 0;
	}
	100% {
		opacity: 1;
	}
}

@keyframes pulse-reverse {
	0% {
		opacity: 0;
	}
	50% {
		opacity: 1;
	}
	100% {
		opacity: 0;
	}
}

</style>
