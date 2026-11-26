/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getContainer } from '@nextcloud/e2e-test-server/docker'
import http from 'node:http'

export const STATIC_SITE_GENERATOR_TOKEN = 'e2e-test-secret'

const STATIC_SITE_GENERATOR_PORT = 8088
const CALLBACK_DELAY_MS = 2000

/**
 * Starts a minimal stand-in for the external static site generator service, so publishing
 * succeeds against the ephemeral Nextcloud container Playwright starts for tests. Accepts any
 * bearer token.
 *
 * @return {Promise<() => Promise<void>>} stop function, closes the server
 */
export async function startMockStaticSiteGenerator() {
	const server = http.createServer((request, response) => {
		const chunks = []
		request.on('data', (chunk) => chunks.push(chunk))
		request.on('end', () => {
			try {
				handleRequest(request, Buffer.concat(chunks), response)
			} catch (error) {
				console.error('Mock static site generator failed to handle request', error)
				sendError(response, 500, 'internal error')
			}
		})
	})

	await new Promise((resolve, reject) => {
		server.once('error', reject)
		server.listen(STATIC_SITE_GENERATOR_PORT, '0.0.0.0', resolve)
	})

	return () => new Promise((resolve) => server.close(() => resolve()))
}

/**
 * URL the mock static site generator is reachable at from inside the Nextcloud container, via
 * its Docker bridge gateway IP.
 *
 * @return {Promise<string>}
 */
export async function getMockStaticSiteGeneratorUrl() {
	const { NetworkSettings } = await getContainer().inspect()
	const gatewayIp = NetworkSettings.Networks?.bridge?.Gateway || NetworkSettings.Gateway || '172.17.0.1'
	return `http://${gatewayIp}:${STATIC_SITE_GENERATOR_PORT}`
}

const STATIC_SITES_PATH = /^\/static-sites\/?$/
const STATIC_SITE_PATH = /^\/static-sites\/([^/]+)$/

/** Static sites the mock knows, by static_site_id, with their slug */
const staticSites = new Map()

/**
 * Routes GET /health and the create, update and delete requests of static sites, the endpoints
 * the generator client calls. Each accepted request is answered with 202 and reported to its
 * callback_status_url shortly after, like the real generator does.
 *
 * @param {import('node:http').IncomingMessage} request - the incoming request
 * @param {Buffer} body - the fully read request body
 * @param {import('node:http').ServerResponse} response - the response to write to
 */
function handleRequest(request, body, response) {
	if (request.method === 'GET' && request.url === '/health') {
		return sendJson(response, 200, { status: 'ok' })
	}

	const data = JSON.parse(body.length ? body.toString() : '{}').data ?? {}

	if (request.method === 'POST' && STATIC_SITES_PATH.test(request.url)) {
		if (staticSites.has(data.static_site_id)) {
			return sendError(response, 409, 'static site already exists')
		}
		if ([...staticSites.values()].includes(data.slug)) {
			return sendError(response, 409, 'slug already taken')
		}
		staticSites.set(data.static_site_id, data.slug)
		return accept(response, data.callback_status_url, publishedStatus(data.slug))
	}

	const staticSiteId = STATIC_SITE_PATH.exec(request.url)?.[1]
	if (staticSiteId !== undefined && ['PATCH', 'DELETE'].includes(request.method)) {
		if (!staticSites.has(staticSiteId)) {
			return sendError(response, 404, 'static site not found')
		}
		if (request.method === 'PATCH') {
			return accept(response, data.callback_status_url, publishedStatus(staticSites.get(staticSiteId)))
		}
		staticSites.delete(staticSiteId)
		return accept(response, data.callback_status_url, { status: 'deleted', data: {} })
	}

	sendError(response, 404, 'not found')
}

/**
 * @param {string} slug - slug of the static site, used to derive a fake publish_url
 * @return {object} status callback body of a successful build
 */
function publishedStatus(slug) {
	return { status: 'published', data: { publish_url: `https://example.test/sites/${slug}/` } }
}

/**
 * Answers 202 and reports the status to the callback URL after a delay.
 *
 * @param {import('node:http').ServerResponse} response - the response to write to
 * @param {string|undefined} callbackStatusUrl - URL to PATCH the status to
 * @param {object} status - status callback body
 */
function accept(response, callbackStatusUrl, status) {
	console.log('Mock static site generator: request accepted', { callbackStatusUrl, status: status.status })
	if (callbackStatusUrl) {
		setTimeout(() => sendCallback(callbackStatusUrl, status), CALLBACK_DELAY_MS)
	}
	response.writeHead(202)
	response.end()
}

/**
 * Simulates the generator reporting the outcome of a request back via the callback_status_url.
 *
 * @param {string} callbackStatusUrl - URL to PATCH the status to
 * @param {object} status - status callback body
 */
async function sendCallback(callbackStatusUrl, status) {
	try {
		const response = await fetch(callbackStatusUrl, {
			method: 'PATCH',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
				'OCS-APIREQUEST': 'true',
			},
			body: JSON.stringify(status),
		})
		console.log('Mock static site generator: callback sent', callbackStatusUrl, '->', response.status)
	} catch (error) {
		console.error('Mock static site generator: callback failed', callbackStatusUrl, error)
	}
}

/**
 * @param {import('node:http').ServerResponse} response - the response to write to
 * @param {number} status - HTTP status code
 * @param {string} errorMessage - error message as the real generator sends it
 */
function sendError(response, status, errorMessage) {
	sendJson(response, status, { data: { error_message: errorMessage } })
}

/**
 * Writes a JSON response body with matching Content-Length.
 *
 * @param {import('node:http').ServerResponse} response - the response to write to
 * @param {number} status - HTTP status code
 * @param {object} body - body to serialize as JSON
 */
function sendJson(response, status, body) {
	const payload = JSON.stringify(body)
	response.writeHead(status, {
		'Content-Type': 'application/json',
		'Content-Length': Buffer.byteLength(payload),
	})
	response.end(payload)
}
