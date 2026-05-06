/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defaultOptions, path } from './defaultOptions.js'

export type ShareContext = { isPublic: true, shareTokenParam: string }
export type CollectiveContext = { isPublic: false, collectiveId: number }
export type Context = ShareContext | CollectiveContext
export type ConstructorForCollective<Client> = {
	new (c: CollectiveContext): Client
}
export type ConstructorForShare<Client> = {
	new (c: { token: string }): Client
}

/**
 * Returns a factory function for an api based on the context
 *
 * @param apis classes to instantiate clients for
 * @param apis.forCollective class to use for collective contexts
 * @param apis.forShare class to use for share contexts
 */
export function clientForContextFactory<ClientForCollective, ClientForShare>({
	forCollective,
	forShare,
}: {
	forCollective: ConstructorForCollective<ClientForCollective>
	forShare: ConstructorForShare<ClientForShare>
}) {
	return ((c: Context) => (c.isPublic)
		? new forShare({ token: c.shareTokenParam })
		: new forCollective(c)
	) as <C extends Context>(c: C) => C extends ShareContext ? ClientForShare : ClientForCollective
}

export class Client<Identifier> {
	readonly #identifier: Identifier

	constructor(identifier: Identifier) {
		this.#identifier = identifier
	}

	/**
	 * Options for a public endpoint
	 *
	 * @param pathOptions Additional options for the path.
	 * @param body body of the request
	 */
	options<P extends object = object, B extends object | undefined = undefined>(
		pathOptions?: P,
		body?: B,
	) {
		pathOptions ??= {} as P
		return {
			...defaultOptions,
			path: { ...path, ...this.#identifier, ...pathOptions },
			...(body ? { body } : {} as { body: B }),
		}
	}
}
