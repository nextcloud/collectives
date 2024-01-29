<?php

declare(strict_types=1);

namespace OCA\Collectives\Search\FileSearch;

use OCP\Files\File;
use OCP\Files\NotFoundException;
use PDO;
use TeamTNT\TNTSearch\Support\TokenizerInterface;
use TeamTNT\TNTSearch\TNTSearch;

/**
 * @property PDO|null $index;
 */
class FileSearcher extends TNTSearch {
	public const DEFAULT_CONFIG = [
		'tokenizer' => WordTokenizer::class,
		'wal' => false,
		'driver' => 'filesystem',
		'storage' => ''
	];

	protected FileIndexer $indexer;

	public function __construct() {
		parent::__construct();
		$this->loadConfig();
		$this->asYouType(true);
		$this->fuzziness(true);
	}

	public function loadConfig(array $config = self::DEFAULT_CONFIG): void {
		parent::loadConfig($config);
		$this->indexer = new FileIndexer($this->engine);
		$this->indexer->loadConfig($config);
	}

	/**
	 * @param string $phrase
	 * @param int    $numOfResults
	 */
	public function search($phrase, $numOfResults = 1000): array {
		$this->setStemmer();
		$this->setTokenizer();
		return parent::search($phrase, $numOfResults);
	}

	/**
	 * @throws FileSearchException
	 */
	public function selectIndexFile(File $indexFile): FileIndexer {
		try {
			$path = $indexFile->getStorage()->getLocalFile($indexFile->getInternalPath());
		} catch (NotFoundException) {
			throw new FileSearchException('File searcher could not find storage for index file.');
		}

		if (!$path) {
			throw new FileSearchException('File searcher could not create local index.');
		}

		return $this->selectIndex($path);
	}

	/**
	 * @param string $indexName
	 * @throws FileSearchException
	 */
	public function selectIndex($indexName): FileIndexer {
		if (!file_exists($indexName)) {
			throw new FileSearchException('Could not find an index for the collective.');
		}
		$this->index = new PDO('sqlite:' . $indexName);
		$this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$this->indexer->setIndex($this->index);
		return $this->indexer;
	}

	/**
	 * @param string $indexName
	 * @param bool $disableOutput
	 */
	public function createIndex($indexName = '', $disableOutput = false): FileIndexer {
		$this->indexer->createIndex($indexName);
		$this->index = $this->indexer->getIndex();
		return $this->indexer;
	}

	public function createInMemoryIndex(): FileIndexer {
		return $this->createIndex(':memory:');
	}

	public function getTokenizer(): ?TokenizerInterface {
		$this->index && $this->setTokenizer();
		$configTokenizer = $this->config['tokenizer'];
		$tokenizer = $this->tokenizer ?: new $configTokenizer();
		return ($tokenizer instanceof TokenizerInterface) ? $tokenizer : null;
	}
}
