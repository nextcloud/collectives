/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: MIT
 */

import {
	configureNextcloud,
	runExec,
	startNextcloud,
	stopNextcloud,
	waitOnNextcloud,
} from '@nextcloud/e2e-test-server/docker'

const serverBranch = process.env.PLAYWRIGHT_NC_SERVER_BRANCH ?? 'master'
const textBranch = serverBranch === 'master' ? 'main' : serverBranch

/**
 * Starts the Nextcloud server.
 */
async function start() {
	return await startNextcloud(serverBranch, true, {
		exposePort: 8089,
	})
}

/**
 * Stops the Nextcloud server and exits the process.
 */
async function stop() {
	process.stderr.write('Stopping Nextcloud server…\n')
	await stopNextcloud()
	process.exit(0)
}

process.on('SIGTERM', stop)
process.on('SIGINT', stop)

// Start the Nextcloud docker container
const ip = await start()
await waitOnNextcloud(ip)
await runExec(['git', 'clone', '--depth=1', `--branch=${serverBranch}`, 'https://github.com/nextcloud/circles.git', 'apps/circles'], { verbose: true })
await runExec(['git', 'clone', '--depth=1', `--branch=${textBranch}`, 'https://github.com/nextcloud/text.git', 'apps/text'], { verbose: true })
await configureNextcloud(['collectives', 'circles', 'text', 'viewer'])

// Idle to wait for shutdown
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
