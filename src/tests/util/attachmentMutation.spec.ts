/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { AttachmentSaveError, mutateAttachment } from '../../util/attachmentMutation.ts'

describe('attachment persistence', () => {
	it.each([false, new Error('offline')])('does not mutate files when preflight saving fails: %s', async (failure) => {
		const save = typeof failure === 'boolean' ? vi.fn().mockResolvedValue(failure) : vi.fn().mockRejectedValue(failure)
		const mutate = vi.fn()
		const update = vi.fn()
		await expect(mutateAttachment(save, mutate, update)).rejects.toThrow()
		expect(mutate).not.toHaveBeenCalled()
		expect(update).not.toHaveBeenCalled()
	})

	it.each([false, new Error('offline')])('reports partial completion if the final save fails: %s', async (failure) => {
		const save = vi.fn().mockResolvedValueOnce(true)
		if (typeof failure === 'boolean') {
			save.mockResolvedValueOnce(failure)
		} else {
			save.mockRejectedValueOnce(failure)
		}
		const mutate = vi.fn().mockResolvedValue({ name: 'renamed.png' })
		const update = vi.fn()
		await expect(mutateAttachment(save, mutate, update)).rejects.toBeInstanceOf(AttachmentSaveError)
		expect(mutate).toHaveBeenCalledOnce()
		expect(update).toHaveBeenCalledWith({ name: 'renamed.png' })
	})

	it('reports success only after references have been saved', async () => {
		const order: string[] = []
		const save = vi.fn(async () => {
			order.push('save')
			return true
		})
		const result = await mutateAttachment(save, async () => {
			order.push('file')
			return 'renamed.png'
		}, () => {
			order.push('references')
		})
		expect(result).toBe('renamed.png')
		expect(order).toEqual(['save', 'file', 'references', 'save'])
	})

	it('does not change references if the file operation fails', async () => {
		const update = vi.fn()
		await expect(mutateAttachment(
			vi.fn().mockResolvedValue(true),
			async () => {
				throw new Error('File operation failed')
			},
			update,
		)).rejects.toThrow('File operation failed')
		expect(update).not.toHaveBeenCalled()
	})
})
