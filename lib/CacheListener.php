<?php

namespace OCA\Collectives;

use OCA\Collectives\Mount\CollectiveStorage;
use OCP\Files\Cache\CacheInsertEvent;
use OCP\Files\Cache\CacheUpdateEvent;
use OCP\Files\Cache\ICacheEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CacheListener {
	private $eventDispatcher;

	/**
	 * CacheListener constructor.
	 *
	 * @param EventDispatcher $eventDispatcher
	 */
	public function __construct(EventDispatcher $eventDispatcher) {
		$this->eventDispatcher = $eventDispatcher;
	}

	public function listen(): void {
		$this->eventDispatcher->addListener(CacheInsertEvent::class, [$this, 'onCacheEvent'], 99999);
		$this->eventDispatcher->addListener(CacheUpdateEvent::class, [$this, 'onCacheEvent'], 99999);
	}

	/**
	 * @param ICacheEvent $event
	 */
	public function onCacheEvent(ICacheEvent $event): void {
		if (!$event->getStorage()->instanceOfStorage(CollectiveStorage::class)) {
			return;
		}

		$jailedPath = preg_replace('/^appdata_\w+\/collectives\/\d+\//', '', $event->getPath());
		if ($jailedPath !== $event->getPath()) {
			$event->setPath($jailedPath);
		}
	}
}
