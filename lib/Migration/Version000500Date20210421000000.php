<?php

declare(strict_types=1);

namespace OCA\Collectives\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCA\Circles\Db\CirclesRequest;
use OCA\Circles\Exceptions\CircleAlreadyExistsException;
use OCA\Circles\Exceptions\CircleDoesNotExistException;
use OCA\Circles\Exceptions\ConfigNoCircleAvailableException;
use OCA\Collectives\Db\CollectiveMapper;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000500Date20210421000000 extends SimpleMigrationStep {
	/** @var bool */
	private $emojiMigration = false;

	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var CirclesRequest */
	private $circlesRequest;

	public function __construct(CollectiveMapper $collectiveMapper,
								CirclesRequest $circlesRequest) {
		$this->collectiveMapper = $collectiveMapper;
		$this->circlesRequest = $circlesRequest;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array   $options
	 *
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('collectives');
		if (!$table->hasColumn('emoji')) {
			$table->addColumn('emoji', Types::STRING, [
				'notnull' => false,
				'length' => 8,
			]);

			$this->emojiMigration = true;
		}
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure
	 * @param array   $options
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		if (!$this->emojiMigration) {
			return;
		}

		// Split emojis and names in existing collectives
		foreach ($this->collectiveMapper->getAll() as $collective) {
			$oldName = $this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId());
			[$name, $emoji] = EmojiSplitter::split($oldName);
			if ($emoji) {
				try {
					$circle = $this->circlesRequest->getCircleFromUniqueId($collective->getCircleUniqueId());
					$circle->setName($name);
					$this->circlesRequest->updateCircle($circle);
				} catch (CircleAlreadyExistsException | CircleDoesNotExistException | ConfigNoCircleAvailableException $e) {
					return;
				}
				$collective->setEmoji($emoji);
				$this->collectiveMapper->update($collective);
			}
		}
	}
}
