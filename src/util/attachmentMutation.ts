/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export class AttachmentSaveError extends Error {
	constructor(public cause: unknown) {
		super('The attachment changed, but saving its page references failed.')
	}
}

/**
 * Confirm editor connectivity before changing a file and persistence afterwards.
 *
 * @param save Verify that the ready editor contents are persisted.
 * @param mutate File operation.
 * @param updateReferences Update the document using the file operation's result.
 */
export async function mutateAttachment<T>(save: () => Promise<boolean>, mutate: () => Promise<T>, updateReferences: (result: T) => void) {
	if (await save() !== true) {
		throw new Error('Could not save the page before changing the attachment.')
	}
	const result = await mutate()
	try {
		updateReferences(result)
		if (await save() !== true) {
			throw new Error('The editor did not confirm saving.')
		}
	} catch (cause) {
		throw new AttachmentSaveError(cause)
	}
	return result
}
