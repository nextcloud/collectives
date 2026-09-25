<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Dav;

use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\DAV\Connector\Sabre\FilesPlugin;
use OCA\DAV\Connector\Sabre\Node;
use OCP\IUserSession;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class PropFindPlugin extends ServerPlugin {
	private ?array $allowDownloadPerCollectiveId = null;
	private ?string $collectiveFolderInternalPath = null;
	private Server $server;

	public function __construct(
		private readonly IUserSession $userSession,
		private readonly UserFolderHelper $userFolderHelper,
		private readonly CollectiveHelper $collectiveHelper,
	) {
	}

	public function initialize(Server $server): void {
		$this->server = $server;
		$server->on('propFind', $this->propFind(...), 200);
		$server->on('beforeCopy', $this->beforeCopy(...), 200);
		$server->on('beforeMove', $this->beforeMove(...), 200);
		$server->on('beforeMethod:GET', $this->beforeGet(...), 200);
	}

	public function beforeCopy(string $source, string $destination): void {
		$node = $this->server->tree->getNodeForPath($source);

		if (!$node instanceof Node) {
			return;
		}

		if ($this->isDownloadDisabled($node)) {
			throw new Forbidden('Copying this item is not allowed');
		}
	}

	public function beforeMove(string $source, string $destination): void {
		$node = $this->server->tree->getNodeForPath($source);

		if (!$node instanceof Node) {
			return;
		}

		if (!$this->isDownloadDisabled($node)) {
			return;
		}

		// Moving within the same collective (e.g. renaming or reordering pages)
		// is fine. Only block moving the item out of the collective, which would
		// otherwise bypass the download restriction.
		$sourceCollectiveId = $this->getCollectiveId($node);
		if ($sourceCollectiveId !== null
			&& $sourceCollectiveId === $this->getDestinationCollectiveId($destination)) {
			return;
		}

		throw new Forbidden('Moving this item out of the collective is not allowed');
	}

	public function beforeGet(RequestInterface $request, ResponseInterface $response): void {
		try {
			$node = $this->server->tree->getNodeForPath($request->getPath());
		} catch (NotFound) {
			return;
		}

		if (!$node instanceof Node) {
			return;
		}

		if ($node->getFileInfo()->getType() === 'dir') {
			return;
		}

		if ($this->isDownloadDisabled($node)) {
			throw new Forbidden('Downloading this file is not allowed');
		}
	}

	private function isDownloadDisabled(Node $node): bool {
		// Check root collective folder
		if ($node->getFileInfo()->getPath() === $this->getCollectiveFolderInternalPath()) {
			return true;
		}

		$collectiveId = $this->getCollectiveId($node);
		if ($collectiveId === null) {
			return false;
		}

		$allowDownload = $this->getAllowDownloadPerCollectiveId()[$collectiveId] ?? false;

		return !$allowDownload;
	}

	/**
	 * Returns the collective id a node belongs to, or null if it isn't part of
	 * a collective mount.
	 */
	private function getCollectiveId(Node $node): ?int {
		$mountPoint = $node->getFileInfo()->getMountPoint();
		if ($mountPoint->getMountType() !== 'collective') {
			return null;
		}

		return $mountPoint->getStorage()
			->getWrapperStorage()
			->getFolderId();
	}

	/**
	 * Returns the collective id the move destination would live in, by resolving
	 * the destination's parent node. Returns null if it isn't a collective.
	 */
	private function getDestinationCollectiveId(string $destination): ?int {
		$parent = dirname($destination);
		try {
			$parentNode = $this->server->tree->getNodeForPath($parent);
		} catch (NotFound) {
			return null;
		}

		if (!$parentNode instanceof Node) {
			return null;
		}

		return $this->getCollectiveId($parentNode);
	}

	public function propFind(PropFind $propFind, INode $node) {
		if (!$node instanceof Node) {
			return;
		}

		$propFind->handle(FilesPlugin::SHARE_HIDE_DOWNLOAD_PROPERTYNAME,
			function () use ($node, $propFind) {
				if ($this->isDownloadDisabled($node)) {
					return 'true';
				}

				return $propFind->get(FilesPlugin::SHARE_HIDE_DOWNLOAD_PROPERTYNAME);
			});
	}

	private function getCollectiveFolderInternalPath(): string {
		if ($this->collectiveFolderInternalPath !== null) {
			return $this->collectiveFolderInternalPath;
		}

		$userId = $this->userSession->getUser()->getUID();

		return $this->collectiveFolderInternalPath = $this->userFolderHelper
			->get($userId)
			->getPath();
	}

	private function getAllowDownloadPerCollectiveId(): array {
		if ($this->allowDownloadPerCollectiveId !== null) {
			return $this->allowDownloadPerCollectiveId;
		}

		$userId = $this->userSession->getUser()->getUID();
		$collectives = $this->collectiveHelper->getCollectivesForUser($userId, true, false);
		$this->allowDownloadPerCollectiveId = [];
		foreach ($collectives as $collective) {
			$this->allowDownloadPerCollectiveId[$collective->getId()] = $collective->canDownload();
		}

		return $this->allowDownloadPerCollectiveId;
	}
}
