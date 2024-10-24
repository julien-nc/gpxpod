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

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCP\IDBConnection;

/**
 * @extends QBMapper<Directory>
 */
class DirectoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'gpxpod_directories', Directory::class);
	}

	/**
	 * @param int $id
	 * @return Directory
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getDirectory(int $id): Directory {
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
	 * @return Directory
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getDirectoryOfUser(int $id, string $userId): Directory {
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
	 * @return Directory[]
	 * @throws Exception
	 */
	public function getDirectoriesOfUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $path
	 * @param string $userId
	 * @return Directory
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getDirectoryOfUserByPath(string $path, string $userId): Directory {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('path', $qb->createNamedParameter($path, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		/** @var Directory $directory */
		$directory = $this->findEntity($qb);
		return $directory;
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @return int
	 * @throws Exception
	 */
	public function deleteAndCleanup(int $id, string $userId): int {
		$qb = $this->db->getQueryBuilder();

		// TODO delete related tracks

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
	 * @param string $path
	 * @param string $user
	 * @param bool $isOpen
	 * @param int $sortOrder
	 * @param bool $sortAscending
	 * @param bool $displayRecursive
	 * @return Directory
	 * @throws Exception
	 */
	public function createDirectory(
		string $path, string $user, bool $isOpen = false, int $sortOrder = 0,
		bool $sortAscending = true, bool $displayRecursive = false,
	): Directory {
		$dir = new Directory();
		$dir->setPath($path);
		$dir->setUser($user);
		$dir->setIsOpen($isOpen ? 1 : 0);
		$dir->setSortOrder($sortOrder);
		$dir->setSortAscending($sortAscending ? 1 : 0);
		$dir->setDisplayRecursive($displayRecursive ? 1 : 0);
		return $this->insert($dir);
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @param string|null $path
	 * @param bool|null $isOpen
	 * @param int|null $sortOrder
	 * @param bool|null $sortAscending
	 * @param bool|null $displayRecursive
	 * @return Directory|null
	 * @throws Exception
	 */
	public function updateDirectory(
		int $id, string $userId,
		?string $path = null, ?bool $isOpen = null, ?int $sortOrder = null,
		?bool $sortAscending = null, ?bool $displayRecursive = null,
	): ?Directory {
		if ($path === null && $isOpen === null && $sortOrder === null && $sortAscending === null && $displayRecursive === null) {
			return null;
		}
		try {
			$dir = $this->getDirectoryOfUser($id, $userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
		if ($path !== null) {
			$dir->setPath($path);
		}
		if ($isOpen !== null) {
			$dir->setIsOpen($isOpen ? 1 : 0);
		}
		if ($sortOrder !== null) {
			$dir->setSortOrder($sortOrder);
		}
		if ($sortAscending !== null) {
			$dir->setSortAscending($sortAscending ? 1 : 0);
		}
		if ($displayRecursive !== null) {
			$dir->setDisplayRecursive($displayRecursive ? 1 : 0);
		}
		return $this->update($dir);
	}
}
