/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Generates a random string of 10 alphanumeric characters.
 */
export function randomString(): string {
	return Math.random().toString(36).replace(/[^a-z0-9]+/g, '').substring(1, 11)
}
