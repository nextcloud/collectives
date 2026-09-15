/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: MIT
 */

import {
	configureNextcloud,
	getContainer,
	runExec,
	runOcc,
	startNextcloud,
	stopNextcloud,
	waitOnNextcloud,
} from '@nextcloud/e2e-test-server/docker'
import { execFileSync } from 'node:child_process'
import { createHash } from 'node:crypto'
import { mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const serverBranch = process.env.PLAYWRIGHT_NC_SERVER_BRANCH ?? 'master'
const textRef = process.env.PLAYWRIGHT_TEXT_REF ?? (serverBranch === 'master' ? 'main' : serverBranch)
const textRepository = process.env.PLAYWRIGHT_TEXT_REPOSITORY ?? 'nextcloud/text'
const textRepositoryUrl = `https://github.com/${textRepository}.git`

/**
 *
 */
async function isServerRunning() {
	try {
		const res = await fetch('http://127.0.0.1:8089/status.php')
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
		forceRecreate: true,
	})
}

/**
 * Check out Text and verify the resolved commit before building it.
 */
export async function checkoutText() {
	if (!/^[0-9a-f]{40}$/.test(textRef)) {
		await runExec(['git', 'clone', '--depth=1', `--branch=${textRef}`, textRepositoryUrl, 'apps/text'], { verbose: true })
	} else {
		await runExec(['git', 'init', 'apps/text'], { verbose: true })
		await runExec(['git', '-C', 'apps/text', 'remote', 'add', 'origin', textRepositoryUrl], { verbose: true })
		await runExec(['git', '-C', 'apps/text', 'fetch', '--depth=1', 'origin', textRef], { verbose: true })
		await runExec(['git', '-C', 'apps/text', 'checkout', '--detach', 'FETCH_HEAD'], { verbose: true })
	}
	const { stdout } = await runExec(['git', '-C', 'apps/text', 'rev-parse', 'HEAD'])
	if (!/^[0-9a-f]{40}$/.test(stdout.trim()) || (/^[0-9a-f]{40}$/.test(textRef) && stdout.trim() !== textRef)) {
		throw new Error(`Text checkout resolved to ${stdout.trim()}, expected ${textRef}`)
	}
	return stdout.trim()
}

/**
 * Build Text with the host Node runtime and install its assets in the test server.
 */
export async function buildText() {
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
		const assets = ['js', 'css'].flatMap((path) => readdirSync(join(directory, path), { recursive: true })
			.filter((file) => /\.(?:m?js|css)$/.test(file))
			.map((file) => ({
				path: `${path}/${file}`,
				sha256: createHash('sha256').update(readFileSync(join(directory, path, file))).digest('hex'),
			})))
		if (!assets.some(({ path }) => path === 'js/text-editor.mjs')
			|| !assets.some(({ path }) => /markdownSourceComparison\.worker-.*\.mjs$/.test(path))) {
			throw new Error('The Text build is missing its editor entry or source comparison worker')
		}
		await runExec(['rm', '-rf', 'apps/text/js', 'apps/text/css'])
		await runExec(['mkdir', '-p', 'apps/text/js', 'apps/text/css'])
		for (const path of ['js', 'css']) {
			execFileSync('docker', ['cp', `${directory}/${path}/.`, `${target}/${path}`], { stdio: 'inherit' })
		}
		return assets.toSorted((a, b) => a.path.localeCompare(b.path))
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

/**
 * Prepare a managed runtime and retain its source, installed app and asset identities.
 */
export async function prepareServer() {
	if (await isServerRunning()) {
		throw new Error('Port 8089 already serves Nextcloud. Use baseURL with an explicitly verified external runtime, or stop it first.')
	}
	const ip = await start()
	await waitOnNextcloud(ip)
	await runExec(['git', 'clone', '--depth=1', `--branch=${serverBranch}`, 'https://github.com/nextcloud/password_policy.git', 'apps/password_policy'], { verbose: true })
	const textCommit = await checkoutText()
	const textAssets = process.env.COLLECTIVES_SEMANTIC_E2E === '1' ? await buildText() : []
	await configureNextcloud(['collectives', 'circles', 'files_pdfviewer', 'files_lock', 'notifications', 'text', 'viewer'])
	const identity = await getContainer().inspect()
	const server = await runExec(['git', 'rev-parse', 'HEAD'])
	const status = await runOcc(['status', '--output=json'])
	const apps = await runOcc(['app:list', '--output=json'])
	mkdirSync('test-results', { recursive: true })
	writeFileSync('test-results/runtime.json', JSON.stringify({
		container: identity.Id,
		image: identity.Image,
		server: { branch: serverBranch, commit: server.stdout.trim(), status: JSON.parse(status.stdout) },
		collectives: { commit: execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' }).trim() },
		text: { repository: textRepository, ref: textRef, commit: textCommit, assets: textAssets },
		apps: JSON.parse(apps.stdout),
		comparisonInitialView: process.env.COLLECTIVES_COMPARISON_INITIAL_VIEW,
	}, null, 2))
	console.log('Collectives test runtime ready')
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	process.on('SIGTERM', stop)
	process.on('SIGINT', stop)
	await prepareServer()
	while (true) {
		await new Promise((resolve) => setTimeout(resolve, 5000))
	}
}
