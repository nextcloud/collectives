<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Collectives\Mount\CollectiveMountPoint;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\PageService;
use OCA\Text\Event\MentionEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\IURLGenerator;

/** @template-implements IEventListener<Event|MentionEvent> */
class TextMentionListener implements IEventListener {
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private CollectiveService $collectiveService,
		private PageService $pageService,
		private ?string $userId,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof MentionEvent) {
			return;
		}

		$mountPoint = $event->getFile()->getMountPoint();
		if (!$mountPoint instanceof CollectiveMountPoint) {
			return;
		}

		if (!$this->userId) {
			return;
		}

		$collective = $this->collectiveService->getCollective($mountPoint->getFolderId(), $this->userId);
		$pageInfo = $this->pageService->findByFile($mountPoint->getFolderId(), $event->getFile(), $this->userId);

		$collectiveLink = $this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . rawurlencode($collective->getName());
		$collectiveName = $collective->getEmoji()
			? $collective->getEmoji() . ' ' . $collective->getName()
			: $collective->getName();

		$pageLink = $this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . $this->pageService->getPageLink($collective->getUrlPath(), $pageInfo);
		$pageName = $pageInfo->getEmoji()
			? $pageInfo->getEmoji() . ' ' . $pageInfo->getTitle()
			: $pageInfo->getTitle();

		$notification = $event->getNotification();
		$notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('collectives', 'collectives-dark.svg')));
		$notification->setLink($pageLink);
		$notification->setRichSubject($this->l10n->t('{user} has mentioned you in {collective} - {page}'), array_merge(
			$notification->getRichSubjectParameters(),
			[
				'collective' => [
					'id' => (string)$collective->getId(),
					'type' => 'highlight',
					'name' => $collectiveName,
					'link' => $collectiveLink,
				],
				'page' => [
					'id' => (string)$pageInfo->getId(),
					'type' => 'highlight',
					'name' => $pageName,
					'link' => $pageLink,
				],
			],
		));
	}
}
