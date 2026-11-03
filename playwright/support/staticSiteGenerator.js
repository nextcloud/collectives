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
 * bearer token. Mirrors dev-tools/mock-static-site-generator/mock-static-site-generator.py,
 * which serves the same purpose for the docker-compose based dev environment.
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
				sendJson(response, 500, { error: 'internal error' })
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

/**
 * Routes GET /health and POST /build, the only two endpoints the generator client calls.
 *
 * @param {import('node:http').IncomingMessage} request - the incoming request
 * @param {Buffer} body - the fully read request body
 * @param {import('node:http').ServerResponse} response - the response to write to
 */
function handleRequest(request, body, response) {
	if (request.method === 'GET' && request.url === '/health') {
		return sendJson(response, 200, { status: 'ok' })
	}

	if (request.method === 'POST' && request.url === '/build') {
		const { callback_status_url: callbackStatusUrl, slug } = JSON.parse(body.length ? body.toString() : '{}')
		console.log('Mock static site generator: build request', { callbackStatusUrl, slug })
		if (callbackStatusUrl) {
			setTimeout(() => sendCallback(callbackStatusUrl, slug), CALLBACK_DELAY_MS)
		}
		return sendJson(response, 200, { status: 'enqueued' })
	}

	sendJson(response, 404, { error: 'not found' })
}

/**
 * Simulates the generator reporting a finished build back via the callback_status_url.
 *
 * @param {string} callbackStatusUrl - URL to PATCH the publication status to
 * @param {string} slug - slug of the static site, used to derive a fake publish_url
 */
async function sendCallback(callbackStatusUrl, slug) {
	try {
		const response = await fetch(callbackStatusUrl, {
			method: 'PATCH',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
				'OCS-APIREQUEST': 'true',
			},
			body: JSON.stringify({
				status: 'published',
				data: { publish_url: `https://example.test/${slug}` },
			}),
		})
		console.log('Mock static site generator: callback sent', callbackStatusUrl, '->', response.status)
	} catch (error) {
		console.error('Mock static site generator: callback failed', callbackStatusUrl, error)
	}
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
