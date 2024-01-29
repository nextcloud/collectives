<?php

declare(strict_types=1);

namespace OCA\Collectives\Search\FileSearch;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;
use PDO;
use TeamTNT\TNTSearch\Contracts\EngineContract;
use TeamTNT\TNTSearch\Indexer\TNTIndexer;
use TeamTNT\TNTSearch\Support\Collection;

/**
 * @property PDO|null $index
 */
class FileIndexer extends TNTIndexer {
	public function __construct(EngineContract $engine) {
		parent::__construct($engine);
		$this->disableOutput(true);
	}

	public function loadConfig(array $config): void {
		parent::loadConfig($config);
		$this->engine->config['storage'] = '';
	}

	private function getDirectoryFiles(Folder $folder, bool $recursive = false): array {
		try {
			$lsNodes = $folder->getDirectoryListing();
		} catch (NotFoundException) {
			return [];
		}

		$files = [];
		$filesRecursive = [];
		foreach ($lsNodes as $node) {
			if ($recursive && $node instanceof Folder) {
				$filesRecursive[] = $this->getDirectoryFiles($node, true);
			}

			$extension = pathinfo($node->getName(), PATHINFO_EXTENSION);
			if ($node instanceof File === false || !in_array($extension, ['md', 'txt'], true)) {
				continue;
			}

			$files[] = $node;
		}

		return array_merge($files, ...$filesRecursive);
	}

	/**
	 * @throws FileSearchException
	 */
	public function runOnDirectory(Folder $folder, bool $recursive = true): void {
		$this->run($this->getDirectoryFiles($folder, $recursive));
	}

	/**
	 * @throws FileSearchException
	 */
	public function run(array $pages = []): void {
		if (!$this->getIndex()) {
			throw new FileSearchException('Indexing could not be performed because index is not selected.');
		}

		$this->getIndex()->exec("CREATE TABLE IF NOT EXISTS filemap (
                    id INTEGER PRIMARY KEY,
                    path TEXT)");
		$this->getIndex()->beginTransaction();

		$processedPages = 0;
		foreach ($pages as $page) {
			try {
				$id = $page->getId();
				$internalPath = $page->getInternalPath();
				$fileCollection = new Collection([
					'id' => $id,
					'name' => $page->getName(),
					'content' => $page->getContent()
				]);
				$this->processDocument($fileCollection);

				$statement = $this->getIndex()->prepare("INSERT INTO filemap ( 'id', 'path') values ( :id, :path)");
				$statement->bindParam(':id', $id);
				$statement->bindParam(':path', $internalPath);
				$statement->execute();

				$processedPages++;
			} catch (NotFoundException|NotPermittedException|InvalidPathException|LockedException $e) {
				throw new FileSearchException('File indexer failed to open and/or read file', 0, $e);
			}
		}
		$this->getIndex()->exec("UPDATE info SET `value`=$processedPages WHERE `key`='total_documents'");
		$this->getIndex()->exec("INSERT INTO info ( 'key', 'value') values ( 'driver', 'filesystem')");

		$this->getIndex()->commit();
	}

	public function getIndex(): ?PDO {
		return $this->engine->index;
	}
}
