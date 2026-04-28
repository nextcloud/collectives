<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $value)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $value)
 * @method string getTerm()
 * @method void setTerm(string $value)
 * @method string getStem()
 * @method void setStem(string $value)
 * @method int getNumHits()
 * @method void setNumHits(int $value)
 * @method int getNumFiles()
 * @method void setNumFiles(int $value)
 */
class SearchWord extends Entity {
	protected ?int $collectiveId = null;
	protected ?string $term = null;
	protected ?string $stem = null;
	protected ?int $numHits = null;
	protected ?int $numFiles = null;
}
