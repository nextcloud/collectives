/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: MIT
 */

import {
	configureNextcloud,
	runExec,
	runOcc,
	startNextcloud,
	stopNextcloud,
	waitOnNextcloud,
} from '@nextcloud/e2e-test-server/docker'
import { startMockStaticSiteGenerator } from './support/staticSiteGenerator.js'

const serverBranch = process.env.PLAYWRIGHT_NC_SERVER_BRANCH ?? 'master'
// Text's development branch matching server master is `main`
const textBranch = serverBranch === 'master' ? 'main' : serverBranch

let stopMockStaticSiteGenerator

/**
 *
 */
async function isServerRunning() {
	try {
		const res = await fetch('https://127.0.0.1:8089/status.php')
		return res.ok
	} catch {
		return false
	}
}

/**
 * Starts the Nextcloud server.
 */
async function start() {
	return await startNextcloud(serverBranch, true, {
		exposePort: 8089,
	})
}

/**
 * Starts a mock of the external static site generator, so publishing a collective as a static
 * website can succeed in tests without a real generator service configured.
 *
 * Only the system config this needs is set here; the app config (generator URL/token) is set
 * later over HTTP, in collective-publish.spec.ts's own beforeAll — see staticSiteGenerator.ts
 * for why `occ config:app:set` alone wouldn't actually reach the server process handling tests.
 */
async function configureStaticSiteGenerator() {
	stopMockStaticSiteGenerator = await startMockStaticSiteGenerator()
	await runOcc(['config:system:set', 'allow_local_remote_servers', '--value', 'true', '--type', 'boolean'])
}

/**
 * Stops the Nextcloud server and exits the process.
 */
async function stop() {
	process.stderr.write('Stopping Nextcloud server…\n')
	await stopMockStaticSiteGenerator?.()
	await stopNextcloud()
	process.exit(0)
}

process.on('SIGTERM', stop)
process.on('SIGINT', stop)

// Start the Nextcloud docker container
if (await isServerRunning()) {
	console.log('└─ Nextcloud is now ready to use')
} else {
	const ip = await start()
	await waitOnNextcloud(ip)
	await runExec(['git', 'clone', '--depth=1', `--branch=${serverBranch}`, 'https://github.com/nextcloud/password_policy.git', 'apps/password_policy'], { verbose: true })
	await runExec(['git', 'clone', '--depth=1', `--branch=${textBranch}`, 'https://github.com/nextcloud/text.git', 'apps/text'], { verbose: true })
	const apps = ['collectives', 'circles', 'files_pdfviewer', 'files_lock', 'notifications', 'text']
	// Nextcloud 35 and older ship the viewer as the separate Viewer app
	if (['stable33', 'stable34', 'stable35'].includes(serverBranch)) {
		apps.push('viewer')
	}
	await configureNextcloud(apps)
}

await configureStaticSiteGenerator()

// Idle to wait for shutdown
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
