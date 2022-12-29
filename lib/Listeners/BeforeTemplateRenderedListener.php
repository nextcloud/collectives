<?php

declare(strict_types=1);


namespace OCA\Collectives\Listeners;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Util;

/** @template-implements IEventListener<Event|BeforeTemplateRenderedEvent> */
class BeforeTemplateRenderedListener implements IEventListener {
	private IUserSession $userSession;
	private IConfig $config;
	private IInitialState $initialState;

	public function __construct(IUserSession $userSession,
								IConfig $config,
								IInitialState $initialState) {
		$this->userSession = $userSession;
		$this->config = $config;
		$this->initialState = $initialState;
	}

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
			$userFolder = $this->config->getUserValue($userId, 'collectives', 'user_folder', '');
		}

		Util::addScript('collectives', 'collectives-files');
		// Provide Collectives user folder as initial state
		$this->initialState->provideInitialState('user_folder', $userFolder);
	}
}
