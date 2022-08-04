<?php

declare(strict_types=1);

namespace OCA\Collectives\AppInfo;

use Closure;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Collectives\CacheListener;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Listeners\CircleDestroyedListener;
use OCA\Collectives\Listeners\BeforeTemplateRenderedListener;
use OCA\Collectives\Listeners\ShareDeletedListener;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Mount\MountProvider;
use OCA\Collectives\Search\CollectiveProvider;
use OCA\Collectives\Search\PageProvider;
use OCA\Collectives\Search\PageContentProvider;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Versions\VersionsBackend;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\IMimeTypeLoader;
use OCP\Share\Events\ShareDeletedEvent;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
	public const APP_NAME = 'collectives';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_NAME, $urlParams);
	}

	/**
	 * @param IRegistrationContext $context
	 */
	public function register(IRegistrationContext $context): void {
		require_once(__DIR__  . '/../../vendor/autoload.php');
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
		$context->registerEventListener(CircleDestroyedEvent::class, CircleDestroyedListener::class);
		$context->registerEventListener(ShareDeletedEvent::class, ShareDeletedListener::class);

		$context->registerService(MountProvider::class, function (ContainerInterface $c) {
			return new MountProvider(
				$c->get(CollectiveHelper::class),
				$c->get(CollectiveFolderManager::class),
				$c->get(IMimeTypeLoader::class),
				$c->get(IAppManager::class),
				$c->get(LoggerInterface::class),
				$c->get(UserFolderHelper::class)
			);
		});

		$context->registerService(VersionsBackend::class, function (ContainerInterface $c) {
			$appManager = $c->get(IAppManager::class);
			if ($appManager->isEnabledForUser('files_versions')) {
				return new VersionsBackend(
					$c->get(CollectiveFolderManager::class),
					$c->get(ITimeFactory::class),
					$c->get(LoggerInterface::class)
				);
			}
		});

		$context->registerSearchProvider(CollectiveProvider::class);
		$context->registerSearchProvider(PageProvider::class);
		$context->registerSearchProvider(PageContentProvider::class);

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
