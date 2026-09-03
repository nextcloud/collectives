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
use OCP\Files\Events\BeforeZipCreatedEvent;
use OCP\IUserSession;

/**
 * Block creation of download archives (zip/tar) that would include the
 * contents of a collective which has downloads disabled.
 *
 * The WebDAV PropFindPlugin only hides the download button in the UI and
 * cannot stop server-side archive downloads (e.g. downloading a parent folder
 * that contains a collective mount). This listener enforces the restriction.
 *
 * @template-implements IEventListener<BeforeZipCreatedEvent|Event>
 */
class BeforeZipCreatedListener implements IEventListener {
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly DownloadCheckService $downloadCheckService,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeZipCreatedEvent)) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		$dir = $event->getDirectory();
		$files = $event->getFiles();

		if (empty($files)) {
			$pathsToCheck = [$dir];
		} else {
			$pathsToCheck = [];
			foreach ($files as $file) {
				$pathsToCheck[] = $dir . '/' . $file;
			}
		}

		if ($this->downloadCheckService->isDownloadBlocked($user->getUID(), $pathsToCheck)) {
			$event->setSuccessful(false);
			$event->setErrorMessage('Downloading this collective is not allowed.');
		}
	}
}
