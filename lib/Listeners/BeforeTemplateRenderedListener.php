<?php

declare(strict_types=1);


namespace OCA\Collectives\Listeners;

use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Text\Event\LoadEditor;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Util;

/** @template-implements IEventListener<Event|BeforeTemplateRenderedEvent> */
class BeforeTemplateRenderedListener implements IEventListener {
	public function __construct(private IUserSession $userSession,
		private UserFolderHelper $userFolderHelper,
		private IEventDispatcher $eventDispatcher,
		private IInitialState $initialState) {
	}

	/**
	 * @throws NotPermittedException
	 */
	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		$userFolder = '';
		if ($event->isLoggedIn()) {
			Util::addStyle('collectives', 'collectives');

			// Get Collectives user folder for users
			$userId = $this->userSession->getUser()
				? $this->userSession->getUser()->getUID()
				: null;
			$userFolder = $this->userFolderHelper->getUserFolderSetting($userId);
		}

		// only available since Nextcloud 28
		if (method_exists(Util::class, 'addInitScript')) {
			Util::addInitScript('collectives', 'collectives-init');
		}

		Util::addScript('collectives', 'collectives-files');

		$isCollectivesResponse = $event->getResponse()->getApp() === Application::APP_NAME;
		if ($isCollectivesResponse && class_exists(LoadEditor::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadEditor());
		}

		// Provide Collectives user folder as initial state
		$this->initialState->provideInitialState('user_folder', $userFolder);
	}
}
