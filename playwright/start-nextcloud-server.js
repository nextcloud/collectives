/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: MIT
 */

import {
	configureNextcloud,
	getContainer,
	runExec,
	startNextcloud,
	stopNextcloud,
	waitOnNextcloud,
} from '@nextcloud/e2e-test-server/docker'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const serverBranch = process.env.PLAYWRIGHT_NC_SERVER_BRANCH ?? 'master'
const textRef = process.env.PLAYWRIGHT_TEXT_REF ?? (serverBranch === 'master' ? 'main' : serverBranch)
const textRepository = process.env.PLAYWRIGHT_TEXT_REPOSITORY ?? 'nextcloud/text'
const textRepositoryUrl = `https://github.com/${textRepository}.git`

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

// eslint-disable-next-line jsdoc/require-jsdoc
async function checkoutText() {
	if (!/^[0-9a-f]{40}$/.test(textRef)) {
		await runExec(['git', 'clone', '--depth=1', `--branch=${textRef}`, textRepositoryUrl, 'apps/text'], { verbose: true })
		return
	}

	await runExec(['git', 'init', 'apps/text'], { verbose: true })
	await runExec(['git', '-C', 'apps/text', 'remote', 'add', 'origin', textRepositoryUrl], { verbose: true })
	await runExec(['git', '-C', 'apps/text', 'fetch', '--depth=1', 'origin', textRef], { verbose: true })
	await runExec(['git', '-C', 'apps/text', 'checkout', '--detach', 'FETCH_HEAD'], { verbose: true })
	const { stdout } = await runExec(['git', '-C', 'apps/text', 'rev-parse', 'HEAD'])
	if (stdout.trim() !== textRef) {
		throw new Error(`Text checkout resolved to ${stdout.trim()}, expected ${textRef}`)
	}
}

/**
 * Build Text with the host Node runtime and install its assets in the test server.
 */
function buildText() {
	const directory = mkdtempSync(join(tmpdir(), 'collectives-text-'))
	const target = `${getContainer().id}:/var/www/html/apps/text`
	try {
		execFileSync('docker', ['cp', `${target}/.`, directory], { stdio: 'inherit' })
		execFileSync('npm', ['ci'], {
			cwd: directory,
			stdio: 'inherit',
			env: { ...process.env, CYPRESS_INSTALL_BINARY: '0' },
		})
		execFileSync('npm', ['run', 'build'], { cwd: directory, stdio: 'inherit' })
		for (const path of ['js', 'css']) {
			execFileSync('docker', ['cp', `${directory}/${path}/.`, `${target}/${path}`], { stdio: 'inherit' })
		}
	} finally {
		rmSync(directory, { recursive: true, force: true })
	}
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
if (await isServerRunning()) {
	console.log('└─ Nextcloud is now ready to use')
} else {
	const ip = await start()
	await waitOnNextcloud(ip)
	await runExec(['git', 'clone', '--depth=1', `--branch=${serverBranch}`, 'https://github.com/nextcloud/password_policy.git', 'apps/password_policy'], { verbose: true })
	await checkoutText()
	if (process.env.COLLECTIVES_SEMANTIC_E2E === '1') {
		buildText()
	}
	await configureNextcloud(['collectives', 'circles', 'files_pdfviewer', 'files_lock', 'notifications', 'text', 'viewer'])
}

// Idle to wait for shutdown
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
