<?php
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
	/** @var FileIndexer */
	protected $indexer;

	public function __construct() {
		parent::__construct();
		$this->indexer = new FileIndexer();
		$this->fuzziness = true;
		$this->asYouType = true;
		$this->loadConfig();
	}

	public function loadConfig(array $config = self::DEFAULT_CONFIG): void {
		$this->indexer->loadConfig($config);
		$this->config = $this->indexer->config;
	}

	public function search($phrase, $numOfResults = 1000): array {
		$this->setStemmer();
		$this->setTokenizer();
		return parent::search($phrase, $numOfResults);
	}

	/**
	 * @param File $indexFile
	 * @return FileIndexer
	 * @throws FileSearchException
	 */
	public function selectIndexFile(File $indexFile): FileIndexer {
		try {
			$path = $indexFile->getStorage()->getLocalFile($indexFile->getInternalPath());
		} catch (NotFoundException $e) {
			throw new FileSearchException('File searcher could not find storage for index file.');
		}

		if (!$path) {
			throw new FileSearchException('File searcher could not create local index.');
		}

		return $this->selectIndex($path);
	}

	/**
	 * @param $path
	 * @return FileIndexer
	 * @throws FileSearchException
	 */
	public function selectIndex($path): FileIndexer {
		$pathToIndex = $path;
		if (!file_exists($pathToIndex)) {
			throw new FileSearchException('Could not find an index for the collective.');
		}
		$this->index = new PDO('sqlite:' . $path);
		$this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$this->indexer->setIndex($this->index);
		return $this->indexer;
	}

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
