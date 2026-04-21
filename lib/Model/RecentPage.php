<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Model;

class RecentPage {
	protected string $collectiveNameWithEmoji = '';
	protected string $pagePath = '';
	protected string $pageUrl = '';
	protected string $title = '';
	protected string $emoji = '';
	protected int $timestamp = 0;

	public function getCollectiveNameWithEmoji(): string {
		return $this->collectiveNameWithEmoji;
	}

	public function setCollectiveNameWithEmoji(string $collectiveNameWithEmoji): self {
		$this->collectiveNameWithEmoji = $collectiveNameWithEmoji;
		return $this;
	}

	public function getPagePath(): string {
		return $this->pagePath;
	}

	public function setPagePath(string $pagePath): self {
		$this->pagePath = $pagePath;
		return $this;
	}

	public function getPageUrl(): string {
		return $this->pageUrl;
	}

	public function setPageUrl(string $pageUrl): self {
		$this->pageUrl = $pageUrl;
		return $this;
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function setTitle(string $title): self {
		$this->title = $title;
		return $this;
	}

	public function getEmoji(): string {
		return $this->emoji;
	}

	public function setEmoji(string $emoji): self {
		$this->emoji = $emoji;
		return $this;
	}

	public function getTimestamp(): int {
		return $this->timestamp;
	}

	public function setTimestamp(int $timestamp): self {
		$this->timestamp = $timestamp;
		return $this;
	}
}
