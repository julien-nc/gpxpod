<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022, Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GpxPod\Db;

use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCP\AppFramework\Db\DoesNotExistException;

class TrackMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'gpxpod_tracks', Track::class);
	}

	/**
	 * @param int $id
	 * @return Track
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getTrack(int $id): Track {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @return Track
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getTrackOfUser(int $id, string $userId): Track {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @return array|\OCP\AppFramework\Db\Entity[]
	 * @throws Exception
	 */
	public function getTracksOfUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @param int $directoryId
	 * @return array|\OCP\AppFramework\Db\Entity[]
	 * @throws Exception
	 */
	public function getDirectoryTracksOfUser(string $userId, int $directoryId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('directory_id', $qb->createNamedParameter($directoryId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $trackPath
	 * @param string $userId
	 * @return Track
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getTrackOfUserByPath(string $userId, string $trackPath): Track {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('trackpath', $qb->createNamedParameter($trackPath, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @return int
	 * @throws Exception
	 */
	public function deleteForUser(int $id, string $userId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $qb->executeStatement();
	}

	/**
	 * @param string $userId
	 * @param int $directoryId
	 * @return int
	 * @throws Exception
	 */
	public function deleteDirectoryTracksForUser(string $userId, int $directoryId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('directory_id', $qb->createNamedParameter($directoryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $qb->executeStatement();
	}

	/**
	 * @param string $trackPath
	 * @param string $userId
	 * @param string $contentHash
	 * @param string $marker
	 * @param bool $isEnabled
	 * @param string|null $color
	 * @param int $colorCriteria
	 * @param int $directoryId
	 * @return mixed|\OCP\AppFramework\Db\Entity|null
	 * @throws Exception
	 */
	public function createTrack(string $trackPath, string $userId, int $directoryId,
								string $contentHash, string $marker,
								bool $isEnabled = false, ?string $color = null, int $colorCriteria = 0): Track {
		try {
			// do not create if one with same path/userId already exists
			$track =  $this->getTrackOfUserByPath($userId, $trackPath);
			throw new Exception('Already exists');
		} catch (MultipleObjectsReturnedException $e) {
			// this shouldn't happen
			throw new Exception('Already exists');
		} catch (DoesNotExistException $e) {
			// does not exist, proceed
		}

		$track = new Track();
		$track->setTrackpath($trackPath);
		$track->setUser($userId);
		$track->setDirectoryId($directoryId);
		$track->setContenthash($contentHash);
		$track->setMarker($marker);
		$track->setIsEnabled($isEnabled ? 1 : 0);
		$track->setColor($color);
		$track->setColorCriteria($colorCriteria);
		return $this->insert($track);
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @param string|null $contentHash
	 * @param string|null $marker
	 * @param bool|null $isEnabled
	 * @param string|null $color
	 * @param int|null $colorCriteria
	 * @param int|null $directoryId
	 * @return mixed|\OCP\AppFramework\Db\Entity
	 * @throws Exception
	 */
	public function updateTrack(int $id, string $userId,
								?string $contentHash = null, ?string $marker = null, ?bool $isEnabled = null,
								?string $color = null, ?int $colorCriteria = null, ?int $directoryId = null): ?Track {
		if ($contentHash === null && $marker === null && $isEnabled === null
			&& $color === null && $colorCriteria === null && $directoryId === null) {
			return null;
		}
		try {
			$track = $this->getTrackOfUser($id, $userId);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
		if ($contentHash !== null) {
			$track->setContenthash($contentHash);
		}
		if ($marker !== null) {
			$track->setMarker($marker);
		}
		if ($isEnabled !== null) {
			$track->setIsEnabled($isEnabled ? 1 : 0);
		}
		if ($color !== null) {
			$track->setColor($color);
		}
		if ($colorCriteria !== null) {
			$track->setColorCriteria($colorCriteria);
		}
		if ($directoryId !== null) {
			$track->setDirectoryId($directoryId);
		}
		return $this->update($track);
	}
}
