<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Collectives\Service\DownloadCheckService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\BeforeDirectFileDownloadEvent;
use OCP\IUserSession;

/**
 * Block direct download of a single file that lives inside a collective which
 * has downloads disabled.
 *
 * The WebDAV PropFindPlugin only hides the download button in the UI and cannot
 * stop server-side downloads. This listener enforces the restriction.
 *
 * @template-implements IEventListener<BeforeDirectFileDownloadEvent|Event>
 */
class BeforeDirectFileDownloadListener implements IEventListener {
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly DownloadCheckService $downloadCheckService,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeDirectFileDownloadEvent)) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		if ($this->downloadCheckService->isDownloadBlocked($user->getUID(), [$event->getPath()])) {
			$event->setSuccessful(false);
			$event->setErrorMessage('Downloading this collective is not allowed.');
		}
	}
}
