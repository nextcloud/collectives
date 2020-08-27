<?php

declare(strict_types=1);

namespace OCA\Collectives\AppInfo;

use Closure;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Mount\MountProvider;
use OCA\Collectives\Search\CollectiveProvider;
use OCA\Collectives\Search\PageProvider;
use OCA\Collectives\Service\CollectiveHelper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\IMimeTypeLoader;
use OCP\IUserSession;
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
		$context->registerService(MountProvider::class, function (ContainerInterface $c) {
			return new MountProvider(
				$c->get(CollectiveHelper::class),
				$c->get(CollectiveFolderManager::class),
				$c->get(IUserSession::class),
				$c->get(IMimeTypeLoader::class)
			);
		});

		$context->registerSearchProvider(CollectiveProvider::class);
		$context->registerSearchProvider(PageProvider::class);
	}

	public function boot(IBootcontext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerMountProvider']));
	}

	private function registerMountProvider(IMountProviderCollection $collection,
										   MountProvider $provider): void {
		$collection->registerProvider($provider);
	}
}
