<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Collectives\Dav\PropFindPlugin;
use OCA\DAV\Events\SabrePluginAddEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Container\ContainerInterface;

/**
 * @link https://docs.nextcloud.com/server/stable/developer_manual/app_development/dav_extension.html
 * @template-implements IEventListener<SabrePluginAddEvent>
 */
class SabrePluginAddListener implements IEventListener {
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof SabrePluginAddEvent)) {
			return;
		}

		$server = $event->getServer();
		$plugin = $this->container->get(PropFindPlugin::class);
		$server->addPlugin($plugin);
	}
}
