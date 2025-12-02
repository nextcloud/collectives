/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useIsDarkTheme } from '@nextcloud/vue'
import { computed } from 'vue'

/**
 * Get luminance for a color
 *
 * @param {number[]} rgbColor color in RGB format
 * @return {number}
 */
function getLuminance(rgbColor) {
	// rgb is [R,G,B] with each value in 0...255
	const [r, g, b] = rgbColor.map((v) => {
		v /= 255
		return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)
	})
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * Get contrast ratio between two colors
 *
 * @param {number[]} rgbColor1 color1 in RGB format
 * @param {number[]} rgbColor2 color2 in RGB format
 * @return {number}
 */
function getContrastRatio(rgbColor1, rgbColor2) {
	const lum1 = getLuminance(rgbColor1)
	const lum2 = getLuminance(rgbColor2)
	const brightest = Math.max(lum1, lum2)
	const darkest = Math.min(lum1, lum2)
	return (brightest + 0.05) / (darkest + 0.05)
}

/**
 * Convert color in hex format to RGB format
 *
 * @param {string} hexColor color in hex format
 * @return {number[]}
 */
function hexToRgb(hexColor) {
	hexColor = hexColor.replace(/^#/, '')
	if (hexColor.length === 3) {
		hexColor = hexColor.split('').map((x) => x + x).join('')
	}
	const num = parseInt(hexColor, 16)
	return [(num >> 16) & 255, (num >> 8) & 255, num & 255]
}

/**
 * @return {{hasContrastToBackground: ComputedRef<function(): boolean>}}
 */
export function useColor() {
	const isDarkTheme = useIsDarkTheme()

	/* This is a computed that returns a function.
	 * If `isDarkTheme` changes it will return a different function
	 * as the contrast changes.
	 */
	const hasContrastToBackground = computed(() => {
		const fallback = isDarkTheme.value ? '000000' : 'FFFFFF'
		const fromDocument = getComputedStyle(document.body)
			.getPropertyValue('--color-main-background')
			.replace('#', '')
		const mainBackgroundColor = fromDocument || fallback
		const rgbBackgroundColor = hexToRgb(mainBackgroundColor)
		return (hexColor) => {
			const rgbColor = hexToRgb(hexColor)
			return getContrastRatio(rgbColor, rgbBackgroundColor) >= 4.5
		}
	})

	return { hasContrastToBackground }
}
