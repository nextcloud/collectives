/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { VitePWA } from 'vite-plugin-pwa'
import { join, resolve } from 'path'

export default createAppConfig(
	{
		init: resolve(join('src', 'init.js')),
		main: resolve(join('src', 'main.js')),
		reference: resolve(join('src', 'reference.js')),
	},
	{
		config: {
			build: {
				rollupOptions: {
					output: {
						manualChunks: {
							vendor: ['vue', 'vue-router'],
						},
					},
				},
			},
			css: {
				modules: {
					localsConvention: 'camelCase',
				},
			},
			plugins: [
				VitePWA({
					// Don't generate a manifest file. It's provided by the theming app already
					manifest: false,
					// Don't try to inject service worker registration. We do it manually at Collectives.vue
					injectRegister: false,
					// Service worker asset destination
					outDir: 'js',
					filename: 'collectives-service-worker.js',
					// Enable service worker in development build
					devOptions: { enabled: true },

					workbox: {
						// Attempt to identify and delete any precaches created by older, incompatible versions
						clientsClaim: true,
						// Include runtime code for the Workbox library in the top-level service worker
						inlineWorkboxRuntime: true,
						// Let all clients use the new service worker version immediately after update
						skipWaiting: true,
						// Precaching
						globPatterns: ["**/*.{js,wasm,css,html,mjs}"],
						//globPatterns: undefined,
						//globDirectory: undefined,
						//globIgnores: undefined,
						maximumFileSizeToCacheInBytes: 5242880,
						// Don't create a sourcemap for the service worker files
						// sourcemap: false,

						// Fallback URL for requests that are not cached.
						// TODO: Doesn't work as expected, needs to point to a precached static HTML file
						navigateFallback: '/index.php/apps/collectives',

						// Configure cache responses for runtime routes
						runtimeCaching: [
							// Cache for all GET requests
							{
								// Strategy: try network first, then cache
								handler: 'NetworkFirst',
								// Cache all requests
								urlPattern: /^.*/,
								options: {
									cacheName: 'collectives',
									// Cache max 10000 requests for one week
									expiration: {
										maxAgeSeconds: 3600 * 24 * 7, // one week
										maxEntries: 10000,
									},
								},
							},
							// Queue outgoing Text API POST requests
							// TODO: test
							{
								// Strategy: network only
								handler: 'NetworkOnly',
								// Cache Text API requests
								urlPattern: /^.*\/apps\/text\/session\/.*/,
								method: 'POST',
								options: {
									backgroundSync: {
										name: 'textApiPostQueue',
										options: {
											maxRetentionTime: 3600, // one hour
										},
									},
								},
							},
							// Queue outgoing Text API PUT requests
							// TODO: test
							{
								// Strategy: network only
								handler: 'NetworkOnly',
								// Cache Text API requests
								urlPattern: /^.*\/apps\/text\/session\/.*/,
								method: 'PUT',
								options: {
									backgroundSync: {
										name: 'textApiPutQueue',
										options: {
											maxRetentionTime: 3600, // one hour
										},
									},
								},
							},
						],
					},
				})
			],
		},
		createEmptyCSSEntryPoints: true,
	},
)
