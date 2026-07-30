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

// Install PHP composer
await runExec(
	['sh', '-c', 'curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer'],
	{ user: 'root', verbose: true },
)

// Download required apps
await runExec(['git', 'clone', '--depth=1', `--branch=${serverBranch}`, 'https://github.com/nextcloud/circles.git', 'apps/circles'], { verbose: true })
await runExec(['git', 'clone', '--depth=1', `--branch=${serverBranch}`, 'https://github.com/nextcloud/files_pdfviewer.git', 'apps/files_pdfviewer'], { verbose: true })
await runExec(['git', 'clone', '--depth=1', `--branch=${serverBranch}`, 'https://github.com/nextcloud/notifications.git', 'apps/notifications'], { verbose: true })
await runExec(['git', 'clone', '--depth=1', `--branch=${serverBranch}`, 'https://github.com/nextcloud/password_policy.git', 'apps/password_policy'], { verbose: true })
await runExec(['git', 'clone', '--depth=1', `--branch=${textBranch}`, 'https://github.com/nextcloud/text.git', 'apps/text'], { verbose: true })

// Install PHP dependencies for apps where required
await runExec(['sh', '-c', 'cd apps/notifications && composer install --no-dev --no-cache --no-interaction'], { verbose: true })

// Configure Nextcloud
await configureNextcloud(['collectives', 'circles', 'files_pdfviewer', 'files_lock', 'notifications', 'text', 'viewer'])

// Idle to wait for shutdown
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
