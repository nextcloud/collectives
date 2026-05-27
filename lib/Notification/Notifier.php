<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Notification;

use OCA\Collectives\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public const SUBJECT_PAGE_UPDATED = 'page_updated';
	public const SUBJECT_PAGE_DELETED = 'page_deleted';

	public function __construct(
		private IFactory $factory,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
	) {
	}

	public function getId(): string {
		return Application::APP_NAME;
	}

	public function getName(): string {
		return $this->factory->get(Application::APP_NAME)->t('Collectives');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_NAME) {
			throw new UnknownNotificationException();
		}

		$l = $this->factory->get(Application::APP_NAME, $languageCode);
		$params = $notification->getSubjectParameters();

		$actingUser = $params['actingUser'];
		$actingDisplayName = $this->userManager->getDisplayName($actingUser) ?? $actingUser;

		$collectiveRichObject = [
			'type' => 'highlight',
			'id' => $params['collectiveId'],
			'name' => $params['collectiveName'],
			'link' => $params['collectiveLink'],
		];

		$pageRichObject = [
			'type' => 'highlight',
			'id' => $params['pageId'],
			'name' => $params['pageTitle'],
		];
		if (!empty($params['pageLink'])) {
			$pageRichObject['link'] = $params['pageLink'];
		}

		$userRichObject = [
			'type' => 'user',
			'id' => $actingUser,
			'name' => $actingDisplayName,
		];

		$richParams = [
			'user' => $userRichObject,
			'collective' => $collectiveRichObject,
			'page' => $pageRichObject,
		];

		$notification->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath('collectives', 'collectives-dark.svg')
			)
		);

		if (!empty($params['pageLink'])) {
			$notification->setLink($params['pageLink']);
		} else {
			$notification->setLink($params['collectiveLink']);
		}

		switch ($notification->getSubject()) {
			case self::SUBJECT_PAGE_UPDATED:
				$notification->setRichSubject(
					$l->t('{user} updated {page} in {collective}'),
					$richParams,
				);
				break;
			case self::SUBJECT_PAGE_DELETED:
				$notification->setRichSubject(
					$l->t('{user} deleted {page} from {collective}'),
					$richParams,
				);
				break;
			default:
				throw new UnknownNotificationException();
		}

		return $notification;
	}
}
