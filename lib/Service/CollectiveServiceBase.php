<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;

class CollectiveServiceBase {
	public function __construct(
		protected CollectiveMapper $collectiveMapper,
		protected CircleHelper $circleHelper,
	) {
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollective(int $id, string $userId): Collective {
		if (null === $collective = $this->collectiveMapper->findByIdAndUser($id, $userId)) {
			throw new NotFoundException('Collective not found: ' . $id);
		}
		$collective->setName($this->collectiveMapper->circleIdToName($collective->getCircleId(), $userId));
		$collective->setLevel($this->circleHelper->getLevel($collective->getCircleId(), $userId));

		return $collective;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectiveFromTrash(int $id, string $userId): Collective {
		if (null === $collective = $this->collectiveMapper->findTrashByIdAndUser($id, $userId)) {
			throw new NotFoundException('Collective not found in trash: ' . $id);
		}
		$collective->setName($this->collectiveMapper->circleIdToName($collective->getCircleId(), $userId));
		$collective->setLevel($this->circleHelper->getLevel($collective->getCircleId(), $userId));

		return $collective;
	}
}
