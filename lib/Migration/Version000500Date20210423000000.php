<?php

declare(strict_types=1);

namespace OCA\Collectives\Migration;

use Closure;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use Psr\Log\LoggerInterface;

class Version000500Date20210423000000 extends SimpleMigrationStep {
	/** @var IDBConnection */
	private $db;

	/** @var ILogger */
	protected $logger;

	public function __construct(IDBConnection $db, LoggerInterface $logger) {
		$this->db = $db;
		$this->logger = $logger;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure
	 * @param array   $options
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$this->logger->info("Starting to migrate emojis.", ["app" => "Collectives"]);

		// Split emojis and names in existing collectives
		foreach ($this->getAll() as $collective) {
			$uniqueId = $collective['circle_unique_id'];
			$oldName = $this->circleUniqueIdToName($uniqueId);
			if (null === $oldName) {
				$this->logger->warning("No circle found for $uniqueId", ["app" => "Collectives"]);
				continue;
			}
			[$name, $emoji] = EmojiSplitter::split($oldName);
			if (null === $emoji) {
				continue;
			}
			try {
				$this->setEmoji($collective['id'], $emoji);
				$this->renameCircle($uniqueId, $name);
			} catch (Exception $e) {
				$this->logger->error("Failed to migrate $oldname.", [
					"app" => "Collectives",
					"exception" => $e
				]);
				continue;
			}
		}

		$this->logger->info("Done migrating emojis.", ["app" => "Collectives"]);
	}

	/**
	 * @return Collective[]
	 */
	private function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('collectives');
		$collectives = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$collectives[] = $data;
		}
		$cursor->closeCursor();
		return $collectives;
	}

	/**
	 * @param string $circleUniqueId
	 *
	 * @return string|null
	 */
	private function circleUniqueIdToName(string $uniqueId): ?string {
		$qb = $this->db->getQueryBuilder();
		$param = $qb->createNamedParameter($uniqueId);
		$qb->selectDistinct('c.unique_id')
			->addSelect('c.name')
			->from('circle_circles', 'c')
			->andWhere($qb->expr()->eq('c.unique_id', $param));
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();
		if ($data === false) {
			return null;
		}
		return $data['name'];
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	private function isCircleUnique(string $name): bool {
		$qb = $this->db->getQueryBuilder();
		$param = $qb->createNamedParameter($name);
		$qb->selectDistinct('c.unique_id')
			->addSelect('c.name')
			->from('circle_circles', 'c');
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()){
			if (strtolower($data['name']) === strtolower($name)){
				return false;
			}
		}
		$cursor->closeCursor();
		return true;
	}

	private function setEmoji(int $id, string $emoji) {
		$qb = $this->db->getQueryBuilder();
		$qb->update('collectives')
			->set('emoji', $qb->createNamedParameter($emoji))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->execute();
	}

	private function renameCircle(string $uniqueId, string $name) {
		// the new name is already taken... Do not rename
		if (!$this->isCircleUnique($name)) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update('circle_circles')
			->set('name', $qb->createNamedParameter($name))
			->where($qb->expr()->eq('unique_id', $qb->createNamedParameter($uniqueId)));
		$qb->execute();
	}
}
