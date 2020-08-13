<?php

namespace OCA\Wiki\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
use OCA\Circles\Model\BaseMember;
use OCA\Wiki\Db\WikiMapper;
use OCP\AppFramework\QueryException;

class WikiCircleHelper {
	/** @var WikiMapper */
	private $wikiMapper;

	public function __construct(WikiMapper $wikiMapper) {
		$this->wikiMapper = $wikiMapper;
	}

	/**
	 * @param string $userId
	 * @param int    $wikiId
	 *
	 * @throws NotFoundException
	 */
	public function userHasWiki(string $userId, int $wikiId): void {
		if (null === $wiki = $this->wikiMapper->findById($wikiId)) {
			throw new NotFoundException('Wiki ' . $wikiId . ' not found');
		}

		// TODO: directly use `Circles::TYPE_USER` once Circles release after 0.19.4 got released
		try {
			$circleMember = Circles::getMember($wiki->getCircleUniqueId(), $userId, BaseMember::TYPE_USER, true);
			if ($userId !== $circleMember->getUserId()) {
				throw new NotFoundException('Wiki ' . $wikiId . ' not found');
			}
		} catch (QueryException | MemberDoesNotExistException $e) {
			throw new NotFoundException('Wiki ' . $wikiId . ' not found');
		}
	}

}
