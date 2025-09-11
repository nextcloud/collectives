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
						// Precache for collectives app assets
						globPatterns: ["**/*.{js,css,mjs}"],
						maximumFileSizeToCacheInBytes: 5242880,

						// Disable navigateFallback (required as precache seems to auto-enable it)
						navigateFallback: null,

						// Don't create a sourcemap for the service worker files
						// sourcemap: false,

						// Configure cache responses for runtime routes
						runtimeCaching: [
							{
								// Cache for navigation requests within the vue router scope

								// Strategy: try network first, then cache
								handler: 'NetworkFirst',
								// Cache assets and API requests
								// urlPattern: /^.*/,
								// urlPattern: /(?:\/index\.php)?\/apps\/collectives/,
								urlPattern: ({ url, request }) => request.mode === 'navigate'
									&& url.pathname.match(/(?:\/index\.php)?\/apps\/collectives/),

								options: {
									cacheName: 'collectives-vue-router',
									// Cache max for one week
									expiration: {
										maxAgeSeconds: 3600 * 24 * 7, // one week
									},
									plugins: [{
										cacheKeyWillBeUsed: async () => {
											// Always use same cache key (apps entrypoint) for vue-router URLs
											// This means we only store one document: the main entry page
											return '/apps/collectives/'
										},
									}],
								},
							},

							{
								// Cache for other apps assets

								// Strategy: try network first, then cache
								handler: 'NetworkFirst',
								// Cache assets and API requests
								// urlPattern: /^.*/,
								urlPattern: /.*\.(js|css|mjs|webp|svg)$/,
								options: {
									cacheName: 'collectives-external-assets',
									// Cache for one week
									expiration: {
										maxAgeSeconds: 3600 * 24 * 7, // one week
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
