<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getId()
 * @method void setId(string $value)
 * @method string getCircleUniqueId()
 * @method void setCircleUniqueId(string $value)
 * @method string getTerm()
 * @method void setTerm(string $value)
 * @method int getNumHits()
 * @method void setNumHits(int $value)
 * @method int getNumFiles()
 * @method void setNumFiles(int $value)
 */
class SearchWord extends Entity {
	protected ?string $circleUniqueId = null;
	protected ?string $term = null;
	protected ?int $numHits = null;
	protected ?int $numFiles = null;
}
