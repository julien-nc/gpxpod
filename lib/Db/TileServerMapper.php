<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Julien Veyssier <julien-nc@posteo.net>
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

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCP\IDBConnection;

/**
 * @extends QBMapper<TileServer>
 */
class TileServerMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'gpxpod_tileservers', TileServer::class);
	}

	/**
	 * @param int $id
	 * @return TileServer
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getTileServer(int $id): TileServer {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		/** @var TileServer $tileServer */
		$tileServer = $this->findEntity($qb);
		return $tileServer;
	}

	/**
	 * @param int $id
	 * @param string|null $userId
	 * @return TileServer
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getTileServerOfUser(int $id, ?string $userId): TileServer {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		if ($userId === null) {
			$qb->andWhere(
				$qb->expr()->isNull('user_id')
			);
		} else {
			$qb->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		}

		/** @var TileServer $tileServer */
		$tileServer = $this->findEntity($qb);
		return $tileServer;
	}

	/**
	 * @param string|null $userId
	 * @return array|Entity[]
	 * @throws Exception
	 */
	public function getTileServersOfUser(?string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());
		if ($userId === null) {
			$qb->where(
				$qb->expr()->isNull('user_id')
			);
		} else {
			$qb->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		}

		return $this->findEntities($qb);
	}

	/**
	 * @param int $id
	 * @param string|null $userId
	 * @return int
	 * @throws Exception
	 */
	public function deleteTileserver(int $id, ?string $userId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		if ($userId === null) {
			$qb->andWhere(
				$qb->expr()->isNull('user_id')
			);
		} else {
			$qb->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		}
		return $qb->executeStatement();
	}

	/**
	 * @param string|null $userId
	 * @param int $type
	 * @param string $name
	 * @param string $url
	 * @param string|null $attribution
	 * @param int|null $minZoom
	 * @param int|null $maxZoom
	 * @return TileServer
	 * @throws Exception
	 */
	public function createTileServer(?string $userId, int $type, string $name, string $url, ?string $attribution,
		?int $minZoom = null, ?int $maxZoom = null): TileServer {
		$tileServer = new TileServer();
		$tileServer->setUserId($userId);
		$tileServer->setType($type);
		$tileServer->setName($name);
		$tileServer->setUrl($url);
		$tileServer->setAttribution($attribution);
		$tileServer->setMinZoom($minZoom);
		$tileServer->setMaxZoom($maxZoom);
		/** @var TileServer $createdTileServer */
		$createdTileServer = $this->insert($tileServer);
		return $createdTileServer;
	}

	/**
	 * @param int $id
	 * @param string|null $userId
	 * @param int $type
	 * @param string $name
	 * @param string $url
	 * @param string|null $attribution
	 * @param int|null $minZoom
	 * @param int|null $maxZoom
	 * @return TileServer
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function updateTileServer(
		int $id, ?string $userId, int $type,
		string $name, string $url, ?string $attribution,
		?int $minZoom, ?int $maxZoom,
	): TileServer {
		$tileServer = $this->getTileServerOfUser($id, $userId);
		$tileServer->setName($name);
		$tileServer->setUrl($url);
		$tileServer->setType($type);
		$tileServer->setAttribution($attribution);
		$tileServer->setMinZoom($minZoom);
		$tileServer->setMaxZoom($maxZoom);
		return $this->update($tileServer);
	}
}
