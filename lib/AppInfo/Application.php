<?php

declare(strict_types=1);

namespace OCA\Unite\AppInfo;

use Closure;
use OCA\Unite\Mount\CollectiveRootPathHelper;
use OCA\Unite\Mount\MountProvider;
use OCA\Unite\Service\CollectiveCircleHelper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
	public const APP_NAME = 'unite';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_NAME, $urlParams);
	}

	/**
	 * @param IRegistrationContext $context
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerService(MountProvider::class, function (ContainerInterface $c) {
			return new MountProvider(
				$c->get(CollectiveCircleHelper::class),
				$c->get(CollectiveRootPathHelper::class),
				$c->get(IRootFolder::class),
				$c->get(IUserSession::class)
			);
		});
	}

	public function boot(IBootcontext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerMountProvider']));
	}

	private function registerMountProvider(IMountProviderCollection $collection,
										   MountProvider $provider): void {
		$collection->registerProvider($provider);
	}
}
