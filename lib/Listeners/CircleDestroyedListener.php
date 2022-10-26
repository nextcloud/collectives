<?php

declare(strict_types=1);


namespace OCA\Collectives\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;

class CircleDestroyedListener implements IEventListener {
	private CollectiveMapper $collectiveMapper;
	private CollectiveFolderManager $collectiveFolderManager;

	public function __construct(CollectiveMapper $collectiveMapper,
								CollectiveFolderManager $collectiveFolderManager) {
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveFolderManager = $collectiveFolderManager;
	}

	/**
	 * @param Event $event
	 *
	 * @throws MissingDependencyException
	 * @throws NotPermittedException
	 */
	public function handle(Event $event): void {
		if (!($event instanceof CircleDestroyedEvent)) {
			return;
		}

		$collective = null;
		try {
			$collective = $this->collectiveMapper->findByCircleId($event->getCircle()->getSingleId());
		} catch (NotFoundException $e) {
		}

		if (!$collective) {
			return;
		}

		// Try to find and delete collective folder
		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
			$collectiveFolder->delete();
		} catch (InvalidPathException | FilesNotFoundException $e) {
		}

		$this->collectiveMapper->delete($collective);
	}
}
