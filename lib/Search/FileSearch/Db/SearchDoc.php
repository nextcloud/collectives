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
 * @method string getCircleUniqueId()
 * @method void setCircleUniqueId(string $value)
 * @method int getWordId()
 * @method void setWordId(int $value)
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
