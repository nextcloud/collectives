<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Dav;

use OCA\Collectives\Dav\PropFindPlugin;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\DAV\Connector\Sabre\FilesPlugin;
use OCA\DAV\Connector\Sabre\Node;
use OCP\Files\FileInfo;
use OCP\Files\Mount\IMountPoint;
use OCP\IUser;
use OCP\IUserSession;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\Tree;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Test\TestCase;

class PropFindPluginTest extends TestCase {
	private IUserSession $userSession;
	private UserFolderHelper $userFolderHelper;
	private CollectiveHelper $collectiveHelper;
	private PropFindPlugin $plugin;

	protected function setUp(): void {
		parent::setUp();
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('jane');
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->method('getUser')->willReturn($user);
		$this->userFolderHelper = $this->createMock(UserFolderHelper::class);
		$this->userFolderHelper->method('getUserFolderSetting')->willReturn('/.Collectives');
		$this->collectiveHelper = $this->createMock(CollectiveHelper::class);
		$this->plugin = new PropFindPlugin($this->userSession, $this->userFolderHelper, $this->collectiveHelper);
	}

	private function node(string $path): Node {
		$mount = $this->createMock(IMountPoint::class);
		$mount->method('getMountType')->willReturn('local');
		$fileInfo = $this->createMock(FileInfo::class);
		$fileInfo->method('getPath')->willReturn($path);
		$fileInfo->method('getMountPoint')->willReturn($mount);
		$node = $this->getMockBuilder(Node::class)->disableOriginalConstructor()->getMock();
		$node->method('getFileInfo')->willReturn($fileInfo);
		return $node;
	}

	public function testUnrelatedNodesDoNotResolveCollectivesFolder(): void {
		$this->userFolderHelper->expects(self::never())->method('get');
		$this->collectiveHelper->expects(self::never())->method('getCollectivesForUser');
		$propFind = new PropFind('', [FilesPlugin::SHARE_HIDE_DOWNLOAD_PROPERTYNAME]);

		$this->plugin->propFind($propFind, $this->node('/jane/files'));

		self::assertNull($propFind->get(FilesPlugin::SHARE_HIDE_DOWNLOAD_PROPERTYNAME));
	}

	public function testRootCollectivesFolderStillDisablesDownloads(): void {
		$this->userFolderHelper->expects(self::never())->method('get');
		$propFind = new PropFind('', [FilesPlugin::SHARE_HIDE_DOWNLOAD_PROPERTYNAME]);

		$this->plugin->propFind($propFind, $this->node('/jane/files/.Collectives'));

		self::assertSame('true', $propFind->get(FilesPlugin::SHARE_HIDE_DOWNLOAD_PROPERTYNAME));
	}

	public function testDavGetDoesNotCheckDownloadPermissions(): void {
		$tree = $this->createMock(Tree::class);
		$tree->expects(self::never())->method('getNodeForPath');
		$server = new Server($tree);
		$this->plugin->initialize($server);

		self::assertTrue($server->emit('beforeMethod:GET', [new Request('GET', '/page.md'), new Response()]));
	}

	public function testAnonymousDavRequestsDoNotResolveUserFolder(): void {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		$plugin = new PropFindPlugin($session, $this->userFolderHelper, $this->collectiveHelper);
		$this->userFolderHelper->expects(self::never())->method('get');
		$this->userFolderHelper->expects(self::never())->method('getUserFolderSetting');
		$propFind = new PropFind('', [FilesPlugin::SHARE_HIDE_DOWNLOAD_PROPERTYNAME]);

		$plugin->propFind($propFind, $this->node('/owner/files/shared'));

		self::assertNull($propFind->get(FilesPlugin::SHARE_HIDE_DOWNLOAD_PROPERTYNAME));
	}
}
