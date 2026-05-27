<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Circles\Model\Member;
use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Notification\Notifier;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class NotificationService {
	public function __construct(
		private readonly INotificationManager $notificationManager,
		private readonly CircleHelper $circleHelper,
		private readonly CollectiveUserSettingsMapper $settingsMapper,
		private readonly IURLGenerator $urlGenerator,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Notify all members of a collective if notifications are enabled.
	 */
	public function notifyMembers(
		Collective $collective,
		PageInfo $pageInfo,
		string $subject,
		string $actingUserId,
		string $pageRelativePath,
	): void {
		try {
			$circle = $this->circleHelper->getCircle($collective->getCircleUniqueId(), null, true);
		} catch (MissingDependencyException|NotFoundException|NotPermittedException $e) {
			$this->logger->warning('Could not fetch circle members for notification: ' . $e->getMessage(), ['exception' => $e]);
			return;
		}

		// Collect direct user member IDs
		$memberUserIds = [];
		foreach ($circle->getMembers() as $member) {
			// TODO: also notify indirect circle/team members
			if ($member->getUserType() === Member::TYPE_USER) {
				$memberUserIds[] = $member->getUserId();
			}
		}

		if (empty($memberUserIds)) {
			return;
		}

		// Fetch all user settings for this collective in one query
		$allSettings = $this->settingsMapper->findByCollectiveId($collective->getId());
		$notifyUserIds = [];
		foreach ($allSettings as $setting) {
			if ($setting->getSetting('notify') === true) {
				$notifyUserIds[] = $setting->getUserId();
			}
		}

		// Build URLs
		$baseUrl = $this->urlGenerator->linkToRouteAbsolute('collectives.start.index');
		$collectiveLink = $baseUrl . rawurlencode($collective->getUrlPath());
		$pageLink = $subject !== Notifier::SUBJECT_PAGE_DELETED
			? $baseUrl . $pageRelativePath
			: '';

		$collectiveNameWithEmoji = $collective->getEmoji()
			? $collective->getEmoji() . ' ' . $collective->getName()
			: $collective->getName();

		$pageTitleWithEmoji = $pageInfo->getEmoji()
			? $pageInfo->getEmoji() . ' ' . $pageInfo->getTitle()
			: $pageInfo->getTitle();

		$subjectParams = [
			'actingUser' => $actingUserId,
			'collectiveId' => (string)$collective->getId(),
			'collectiveName' => $collectiveNameWithEmoji,
			'collectiveLink' => $collectiveLink,
			'pageId' => (string)$pageInfo->getId(),
			'pageTitle' => $pageTitleWithEmoji,
			'pageLink' => $pageLink,
		];

		foreach ($memberUserIds as $userId) {
			// Skip acting and non-notify users
			if ($userId === $actingUserId) {
				continue;
			}
			if (!in_array($userId, $notifyUserIds, true)) {
				continue;
			}

			// Replace existing notifications for this page
			$filter = $this->notificationManager->createNotification();
			$filter->setApp(Application::APP_NAME)
				->setUser($userId)
				->setObject('page', (string)$pageInfo->getId());
			$this->notificationManager->markProcessed($filter);

			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_NAME)
				->setUser($userId)
				->setDateTime(new \DateTime())
				->setObject('page', (string)$pageInfo->getId())
				->setSubject($subject, $subjectParams);

			$this->notificationManager->notify($notification);
		}
	}
}
