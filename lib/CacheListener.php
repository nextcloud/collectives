<?php

declare(strict_types=1);

namespace OCA\Collectives;

use OCA\Collectives\Mount\CollectiveStorage;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Cache\CacheInsertEvent;
use OCP\Files\Cache\CacheUpdateEvent;
use OCP\Files\Cache\ICacheEvent;

class CacheListener {
	private IEventDispatcher $eventDispatcher;

	/**
	 * CacheListener constructor.
	 *
	 * @param IEventDispatcher $eventDispatcher
	 */
	public function __construct(IEventDispatcher $eventDispatcher) {
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
