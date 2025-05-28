<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 * @method int|float getSize()
 * @method void setSize(int|float $size)
 * @method int getMimetype()
 * @method void setMimetype(int $mimetype)
 * @method string getMetadata()
 * @method void setMetadata(string $metadata)
 */
class CollectiveVersion extends Entity implements JsonSerializable {
	protected ?int $fileId = null;
	protected ?int $timestamp = null;
	protected ?int $size = null;
	protected ?int $mimetype = null;
	protected ?string $metadata = null;

	public function getDecodedMetadata(): array {
		return json_decode($this->metadata ?? '', true, 512, JSON_THROW_ON_ERROR) ?? [];
	}

	public function setDecodedMetadata(array $value): void {
		$this->metadata = json_encode($value, JSON_THROW_ON_ERROR);
		$this->markFieldUpdated('metadata');
	}

	/**
	 * @abstract given a key, return the value associated with the key in the metadata column
	 */
	public function getMetadataValue(string $key): ?string {
		return $this->getDecodedMetadata()[$key] ?? null;
	}

	/**
	 * @abstract sets a key value pair in the metadata column
	 */
	public function setMetadataValue(string $key, string $value): void {
		$metadata = $this->getDecodedMetadata();
		$metadata[$key] = $value;
		$this->setDecodedMetadata($metadata);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'fileId' => $this->fileId,
			'timestamp' => $this->timestamp,
			'size' => $this->size,
			'mimetype' => $this->mimetype,
			'metadata' => $this->metadata,
		];
	}
}
