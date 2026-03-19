/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { circlesMemberTypes } from '../constants.js'

type Circle = {
	id: string
	name: string
	displayName: string
	source: number
}

type CircleMember = {
	id: string
	circleId: string
	singleId: string
	userId: string
	userType: number
	displayName: string
	level: number
	basedOn?: Circle
}

/**
 * @param member Member
 */
export function circleMemberType(member: CircleMember): number {
	// If the user type is a circle, this could originate from multiple sources
	// Copied from Contacts app src/models/member.ts get userType()
	return member.userType !== circlesMemberTypes.TYPE_CIRCLE
		? member.userType
		: member.basedOn.source
}

/**
 * @param m1 First member
 * @param m1.userId First member user ID
 * @param m1.displayName First member display name
 * @param m1.level First member level
 * @param m1.userType First member user type
 * @param m2 Second member
 * @param m2.userId Second member user ID
 * @param m2.displayName Second member display name
 * @param m2.level Second member level
 * @param m2.userType Second member user type
 */
export function sortMembersByLevelAndType(m1: CircleMember, m2: CircleMember): number {
	// Sort by level (admin > moderator > member)
	if (m1.level !== m2.level) {
		return m2.level - m1.level
	}

	// Sort by user type (user > group > circle)
	if (circleMemberType(m1) !== circleMemberType(m2)) {
		return circleMemberType(m1) - circleMemberType(m2)
	}

	// Sort by display name
	return m1.displayName.localeCompare(m2.displayName)
}
