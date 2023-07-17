<template>
	<div class="placeholder-main" :class="'placeholder-main-' + type">
		<!-- Placeholder animation -->
		<template v-for="(suffix, gradientIndex) in ['-regular', '-reverse']">
			<svg :key="'gradient' + suffix" :class="'placeholder-gradient placeholder-gradient' + suffix">
				<defs>
					<linearGradient :id="'placeholder-gradient' + suffix">
						<stop offset="0%" :stop-color="(gradientIndex === 0) ? colorPlaceholderLight : colorPlaceholderDark" />
						<stop offset="100%" :stop-color="(gradientIndex === 0) ? colorPlaceholderDark : colorPlaceholderLight" />
					</linearGradient>
				</defs>
			</svg>

			<ul :key="'list' + suffix" :class="'placeholder-list placeholder-list' + suffix">
				<li v-for="(width, index) in placeholderData" :key="'placeholder' + suffix + index">
					<svg v-if="type === 'items'"
						class="items-placeholder"
						xmlns="http://www.w3.org/2000/svg"
						:fill="'url(#placeholder-gradient' + suffix + ')'">
						<circle class="items-placeholder-icon" />
						<rect class="items-placeholder-line-one" :style="width" />
					</svg>
					<svg v-if="type === 'page-heading'"
						class="page-heading-placeholder"
						xmlns="http://www.w3.org/2000/svg"
						:fill="'url(#placeholder-gradient' + suffix + ')'">
						<circle class="page-heading-placeholder-icon" />
						<rect class="page-heading-placeholder-line-one" :style="width" />
						<rect class="page-heading-placeholder-line-two" />
					</svg>
					<svg v-if="type === 'text'"
						class="text-placeholder"
						xmlns="http://www.w3.org/2000/svg"
						:fill="'url(#placeholder-gradient' + suffix + ')'">
						<rect class="text-placeholder-line-one" :style="textLinesWidth[0]" />
						<rect class="text-placeholder-line-two" :style="textLinesWidth[1]" />
						<rect class="text-placeholder-line-three" :style="textLinesWidth[2]" />
						<rect class="text-placeholder-line-four" :style="width" />
					</svg>
				</li>
			</ul>
		</template>
	</div>
</template>

<script>
const bodyStyles = window.getComputedStyle(document.body)
const colorPlaceholderDark = bodyStyles.getPropertyValue('--color-placeholder-dark')
const colorPlaceholderLight = bodyStyles.getPropertyValue('--color-placeholder-light')

export default {
	name: 'SkeletonLoading',

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
		textLinesWidth() {
			return [
				'width: ' + (Math.floor(Math.random() * 50) + 60) + '%',
				'width: ' + (Math.floor(Math.random() * 50) + 60) + '%',
				'width: ' + (Math.floor(Math.random() * 50) + 60) + '%',
			]
		},
	},
}
</script>

<style lang="scss" scoped>
$clickable-area: 40px;
$margin: 8px;
$messages-list-max-width: 670px;

.placeholder-main {
	max-width: $messages-list-max-width;
	position: relative;
	margin-bottom: auto;

	&-text,
	&-page-heading {
		margin: auto;
	}
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

.placeholder-gradient {
	position: fixed;
	height: 0;
	width: 0;
	z-index: -1;
}

.items-placeholder,
.text-placeholder,
.page-heading-placeholder {

	&-icon {
		width: $clickable-area;
		height: $clickable-area;
		cx: calc(#{$clickable-area} / 2);
		cy: calc(#{$clickable-area} / 2);
		r: calc(#{$clickable-area} / 2);
	}
}

.items-placeholder {
	width: calc(100% - 2 * #{$margin});
	height: $clickable-area;
	margin: 2px 0 -1px 0;

	&-line-one {
		width: calc(100% - #{$margin + $clickable-area});
		position: relative;
		height: 1.5em;
		x: $margin + $clickable-area;
		y: 10px;
	}
}

.page-heading-placeholder {
	width: min($messages-list-max-width, 100vw);
	height: calc(#{$clickable-area} * 2);
	margin: 12px 14px;
	display: block;

	&-line-one {
		width: min($messages-list-max-width, 100vw);
		position: relative;
		height: 2em;
		x: $margin + 4 + $clickable-area;
		y: 6px;
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
	height: calc(#{$clickable-area} * 2);
	margin: $margin auto;
	padding: 4px 8px 0 14px;
	display: block;

	&-line-one,
	&-line-two,
	&-line-three,
	&-line-four {
		width: 670px;
		height: 1em;
	}

	&-line-one {
		y: 5px;
		width: 175px;
	}

	&-line-two {
		y: 25px;
	}

	&-line-three {
		y: 45px;
	}

	&-line-four {
		y: 65px;
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
