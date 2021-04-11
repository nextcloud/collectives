<?php

declare(strict_types=1);

namespace OCA\Collectives\AppInfo;

use Closure;
use OCA\Collectives\CacheListener;
use OCA\Collectives\Command\ExpireCollectiveVersions;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Listeners\LoadAdditionalScriptsListener;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Mount\MountProvider;
use OCA\Collectives\Search\CollectiveProvider;
use OCA\Collectives\Search\PageProvider;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use OCA\Collectives\Versions\VersionsBackend;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\IMimeTypeLoader;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
	public const APP_NAME = 'collectives';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_NAME, $urlParams);
	}

	/**
	 * @param IRegistrationContext $context
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);

		$context->registerService(MountProvider::class, function (ContainerInterface $c) {
			return new MountProvider(
				$c->get(CollectiveHelper::class),
				$c->get(CollectiveFolderManager::class),
				$c->get(IMimeTypeLoader::class),
				$c->get(IAppManager::class),
				$c->get(UserFolderHelper::class)
			);
		});

		$context->registerService(VersionsBackend::class, function (ContainerInterface $c) {
			return new VersionsBackend(
				$c->get(CollectiveFolderManager::class),
				$c->get(MountProvider::class),
				$c->get(ITimeFactory::class)
			);
		});

		$context->registerService(ExpireCollectiveVersions::class, function (ContainerInterface $c) {
			return new ExpireCollectiveVersions(
				$c->get(ITimeFactory::class),
				$c->get(CollectiveVersionsExpireManager::class)
			);
		});

		$context->registerService(\OCA\Collectives\BackgroundJob\ExpireCollectiveVersions::class, function (ContainerInterface $c) {
			return new \OCA\Collectives\BackgroundJob\ExpireCollectiveVersions(
				$c->get(ITimeFactory::class),
				$c->get(CollectiveVersionsExpireManager::class)
			);
		});

		$context->registerSearchProvider(CollectiveProvider::class);
		$context->registerSearchProvider(PageProvider::class);

		$cacheListener = $this->getContainer()->get(CacheListener::class);
		$cacheListener->listen();
	}

	public function boot(IBootcontext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerMountProvider']));
	}

	private function registerMountProvider(IMountProviderCollection $collection,
										   MountProvider $provider): void {
		$collection->registerProvider($provider);
	}
}
