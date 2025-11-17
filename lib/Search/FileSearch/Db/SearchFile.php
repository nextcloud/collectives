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
 * @method int getFileId()
 * @method void setFileId(int $value)
 * @method string getPath()
 * @method void setPath(string $value)
 * @method string getMtime()
 * @method void setMtime(int $value)
 */
class SearchFile extends Entity {
	protected ?string $circleUniqueId = null;
	protected ?int $fileId = null;
	protected ?string $path = null;
	protected ?int $mtime = null;
}
