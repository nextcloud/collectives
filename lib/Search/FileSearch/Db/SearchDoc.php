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
 * @method string getWordId()
 * @method void setWordId(string $value)
 * @method int getFileId()
 * @method void setFileId(int $value)
 * @method int getHitCount()
 * @method void setHitCount(int $value)
 */
class SearchDoc extends Entity {
	protected ?string $circleUniqueId = null;
	protected ?string $wordId = null;
	protected ?int $fileId = null;
	protected ?int $hitCount = null;
}
