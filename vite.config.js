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
						// Disable precaching
						globPatterns: undefined,
						globDirectory: undefined,
						globIgnores: undefined,
						// Don't create a sourcemap for the service worker files
						// sourcemap: false,

						// Configure cache responses for runtime routes
						runtimeCaching: [
							// Cache for navigation requests within the vue router scope
							{
								// Strategy: try network first, then cache
								handler: 'NetworkFirst',
								// Cache assets and API requests
								// urlPattern: /^.*/,
								urlPattern: ({ url, request }) => request.mode === 'navigate'
									&& url.pathname.match(/(?:\/index\.php)?\/apps\/collectives/),
								options: {
									cacheName: 'collectives-vue-router',
									// Cache max 10000 requests for one week
									expiration: {
										maxAgeSeconds: 3600 * 24 * 7, // one week
										maxEntries: 10000,
									},
									plugins: [{
										cacheKeyWillBeUsed: async () => {
											// Always use same cache key (apps entrypoint) for vue-router URLs
											// This means we only store one document: the main entry page
											return '/apps/collectives/'
										},
										handlerDidError: async () => {
											// If network fails, respond with cached app entrypoint
											const cache = await self.caches.open('collectives-vue-router')
											return await cache.match('/apps/collectives/') || Response.error()
										},
									}],
								},
							},
							// Cache for any non-navigation requests
							{
								// Strategy: try network first, then cache
								handler: 'NetworkFirst',
								// Cache assets and API requests
								// urlPattern: /^.*/,
								urlPattern: ({ request }) => request.mode !== 'navigate',
								options: {
									cacheName: 'collectives-runtime',
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
