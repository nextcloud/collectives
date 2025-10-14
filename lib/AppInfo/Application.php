<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\AppInfo;

use Closure;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\EditingCircleEvent;
use OCA\Collectives\CacheListener;
use OCA\Collectives\Dashboard\RecentPagesWidget;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveVersionMapper;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Listeners\BeforeTemplateRenderedListener;
use OCA\Collectives\Listeners\CircleDestroyedListener;
use OCA\Collectives\Listeners\CircleEditingEventListener;
use OCA\Collectives\Listeners\CollectivesReferenceListener;
use OCA\Collectives\Listeners\NodeRenamedListener;
use OCA\Collectives\Listeners\ShareDeletedListener;
use OCA\Collectives\Listeners\TextMentionListener;
use OCA\Collectives\Middleware\PublicOCSMiddleware;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Mount\MountProvider;
use OCA\Collectives\Reference\SearchablePageReferenceProvider;
use OCA\Collectives\Search\CollectiveProvider;
use OCA\Collectives\Search\PageContentProvider;
use OCA\Collectives\Search\PageProvider;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\SetupChecks\CirclesAppIsEnableCheck;
use OCA\Collectives\SetupChecks\PDOSQLiteDriverIsEnableCheck;
use OCA\Collectives\Team\CollectiveTeamResourceProvider;
use OCA\Collectives\Trash\PageTrashBackend;
use OCA\Collectives\Trash\PageTrashManager;
use OCA\Collectives\Versions\VersionsBackend;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Text\Event\MentionEvent;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\IMimeTypeLoader;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\SetupCheck\ISetupCheck;
use OCP\Share\Events\ShareDeletedEvent;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

class Application extends App implements IBootstrap {
	public const APP_NAME = 'collectives';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_NAME, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		require_once(__DIR__ . '/../../vendor/autoload.php');
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
		$context->registerEventListener(NodeRenamedEvent::class, NodeRenamedListener::class);
		$context->registerEventListener(CircleDestroyedEvent::class, CircleDestroyedListener::class);
		$context->registerEventListener(EditingCircleEvent::class, CircleEditingEventListener::class);
		$context->registerEventListener(ShareDeletedEvent::class, ShareDeletedListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, CollectivesReferenceListener::class);
		$context->registerEventListener(MentionEvent::class, TextMentionListener::class);

		$context->registerMiddleware(PublicOCSMiddleware::class);

		$context->registerService(MountProvider::class, fn (ContainerInterface $c) => new MountProvider(
			$c->get(CollectiveHelper::class),
			$c->get(CollectiveFolderManager::class),
			$c->get(IMimeTypeLoader::class),
			$c->get(IAppManager::class),
			$c->get(LoggerInterface::class),
			$c->get(UserFolderHelper::class),
			$c->get(IUserSession::class)
		));

		$context->registerService(PageTrashBackend::class, function (ContainerInterface $c) {
			$trashBackend = new PageTrashBackend(
				$c->get(CollectiveFolderManager::class),
				$c->get(PageTrashManager::class),
				$c->get(MountProvider::class),
				$c->get(CollectiveMapper::class),
				$c->get(PageMapper::class),
				$c->get(LoggerInterface::class),
				$c->get(IUserManager::class),
				$c->get(IUserSession::class),
			);
			$hasVersionApp = interface_exists(IVersionBackend::class);
			if ($hasVersionApp) {
				$trashBackend->setVersionsBackend($c->get(VersionsBackend::class));
			}
			return $trashBackend;
		});

		$context->registerService(VersionsBackend::class, function (ContainerInterface $c) {
			if (interface_exists(IVersionBackend::class)) {
				return new VersionsBackend(
					$c->get(CollectiveFolderManager::class),
					$c->get(CollectiveVersionMapper::class),
					$c->get(IMimeTypeLoader::class),
					$c->get(IUserSession::class),
					$c->get(LoggerInterface::class)
				);
			}
		});

		$context->registerSearchProvider(CollectiveProvider::class);
		$context->registerSearchProvider(PageProvider::class);
		$context->registerSearchProvider(PageContentProvider::class);

		$context->registerReferenceProvider(SearchablePageReferenceProvider::class);

		$cacheListener = $this->getContainer()->get(CacheListener::class);
		$cacheListener->listen();

		if (interface_exists(IAPIWidgetV2::class)) {
			$context->registerDashboardWidget(RecentPagesWidget::class);
		}

		if (method_exists($context, 'registerTeamResourceProvider')) {
			$context->registerTeamResourceProvider(CollectiveTeamResourceProvider::class);
		}

		if (interface_exists(ISetupCheck::class) && method_exists($context, 'registerSetupCheck')) {
			/** @psalm-suppress MissingDependency */
			$context->registerSetupCheck(PDOSQLiteDriverIsEnableCheck::class);
			/** @psalm-suppress MissingDependency */
			$context->registerSetupCheck(CirclesAppIsEnableCheck::class);
		}

		$context->registerService(SluggerInterface::class, function () {
			return new AsciiSlugger();
		}, false);
	}

	public function boot(IBootcontext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerMountProvider']));
	}

	private function registerMountProvider(IMountProviderCollection $collection,
		MountProvider $provider): void {
		$collection->registerProvider($provider);
	}
}
