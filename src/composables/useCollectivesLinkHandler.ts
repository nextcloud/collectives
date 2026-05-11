/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Pinia } from 'pinia'
import type { Router } from 'vue-router'
import type { Collective } from '../types.ts'

import { generateUrl } from '@nextcloud/router'
import { useCollectivesStore } from '../stores/collectives.js'
import { useRootStore } from '../stores/root.js'

/**
 * Check whether a URL collective segment (slug or name form) refers to
 * the same collective as `currentCollective`.
 *
 * @param segment - URL-encoded collective segment
 * @param currentCollective - store object with id, name, slug
 */
function isSegmentSameCollective(segment: string, currentCollective: Collective | null | undefined) {
	if (!currentCollective) {
		return false
	}
	const decoded = decodeURIComponent(segment)
	// Try slug form: "CollectiveName-123"
	const slugMatch = decoded.match(/^(.+)-(\d+)$/)
	if (slugMatch) {
		return Number(slugMatch[2]) === currentCollective.id
	}
	// Fall back to name form
	return decoded === currentCollective.name
}

/**
 * Create the smart Collectives link opener.
 * Must be called after pinia and router are set up.
 *
 * @param router Vue Router instance
 * @param pinia Pinia instance
 */
export function createOpenCollectivesLink(router: Router, pinia: Pinia) {
	return function openCollectivesLink(href: string) {
		const rootStore = useRootStore(pinia)
		const collectivesStore = useCollectivesStore(pinia)

		let linkUrl: URL
		try {
			linkUrl = new URL(href, window.location.href)
		} catch {
			window.open(href, '_blank')
			return
		}

		const collectivesBase = generateUrl('/apps/collectives')
		if (!linkUrl.pathname.startsWith(collectivesBase)) {
			window.open(linkUrl.href, '_blank')
			return
		}

		// The path inside the collectives router (everything after /apps/collectives)
		const pathInRouter = linkUrl.pathname.slice(collectivesBase.length) || '/'
		const searchAndHash = linkUrl.search + linkUrl.hash

		// Is the target a public share URL? Matches /p/<token>[/...]
		const publicShareMatch = pathInRouter.match(/^\/p\/([^/]+)(\/.*)?$/)

		if (rootStore.isPublic) {
			const currentToken = rootStore.shareTokenParam

			if (publicShareMatch) {
				// public → public: navigate directly (same or different token, router handles it)
				router.push(pathInRouter + searchAndHash)
			} else {
				// public → internal: check if same collective
				// pathInRouter looks like /<collectiveSegment>[/...]
				const internalMatch = pathInRouter.match(/^\/([^/]+)(\/.*)?$/)
				if (!internalMatch) {
					window.open(linkUrl.href, '_blank')
					return
				}
				const collectiveSegment = internalMatch[1]
				const rest = internalMatch[2] || ''

				if (isSegmentSameCollective(collectiveSegment, collectivesStore.currentCollective)) {
					// Rewrite to /p/<token>/<segment><rest>
					router.push(`/p/${currentToken}/${collectiveSegment}${rest}${searchAndHash}`)
				} else {
					// Different collective — open in new tab
					window.open(linkUrl.href, '_blank')
				}
			}
		} else {
			if (publicShareMatch) {
				// authenticated → public share link: strip /p/<token>
				const rest = publicShareMatch[2] || '/'
				router.push(rest + searchAndHash)
			} else {
				// authenticated → internal: navigate directly
				router.push(pathInRouter + searchAndHash)
			}
		}
	}
}
